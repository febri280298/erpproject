<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Master\Partner;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\SalesOrder;
use App\Services\DocumentNumberService;
use App\Services\Posting\SalesPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class DeliveryOrderController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly SalesPostingService $posting,
    ) {}

    public function index(Request $request): View
    {
        return view('sales.deliveries.index', [
            'documents' => DeliveryOrder::query()
                ->with(['customer:id,name', 'warehouse:id,name', 'salesOrder:id,so_no'])
                ->filter($request->query())
                ->latest('date')->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'customers' => Partner::customers()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /**
     * Dua jalur: menarik sisa item dari sebuah pesanan penjualan, atau membuat
     * surat jalan lepas tanpa pesanan — untuk pengiriman contoh barang,
     * penggantian, atau penjualan langsung yang tidak melewati SO.
     */
    public function create(Request $request): View
    {
        $order = $request->filled('sales_order_id')
            ? SalesOrder::with('items.product.uom', 'customer', 'warehouse')->find($request->query('sales_order_id'))
            : null;

        if ($order && ! $order->canDeliver()) {
            abort(403, 'Pesanan ini tidak memiliki item yang menunggu pengiriman.');
        }

        $manual = $order === null && $request->query('mode') === 'manual';

        return view('sales.deliveries.form', [
            'order' => $order,
            'manual' => $manual,
            // Sisa stok per gudang, supaya orang gudang tahu barangnya cukup
            // atau tidak SEBELUM surat jalannya dibuat. Diambil sekali sebagai
            // peta, bukan per baris: tabel bisa berisi puluhan item dan
            // memanggilnya satu-satu berarti puluhan kueri untuk satu halaman.
            'stok' => $this->petaStok(),
            'openOrders' => SalesOrder::query()
                ->whereIn('status', ['confirmed', 'partial'])
                ->with('customer:id,name')
                ->latest('date')
                ->get(),
            'customers' => Partner::customers()->active()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'defaultWarehouse' => Warehouse::defaultId(),
            'products' => Product::optionsPayload(),
            'nextNumber' => $this->numbers->peek('delivery_order'),
        ]);
    }

    /**
     * Sisa stok seluruh produk per gudang: [gudang_id][produk_id] => jumlah.
     *
     * Dipakai kedua jalur surat jalan. Jalur dari pesanan hanya memerlukan satu
     * gudang, tetapi jalur manual membiarkan gudangnya diganti setelah halaman
     * terbuka, jadi seluruhnya dikirim sekaligus dan dibaca di sisi peramban.
     *
     * @return array<int,array<int,float>>
     */
    private function petaStok(): array
    {
        $peta = [];

        foreach (DB::table('stocks')->select('warehouse_id', 'product_id', 'quantity')->get() as $baris) {
            $peta[(int) $baris->warehouse_id][(int) $baris->product_id] = (float) $baris->quantity;
        }

        return $peta;
    }

    public function store(Request $request): RedirectResponse
    {
        $fromOrder = $request->filled('sales_order_id');

        $data = $request->validate([
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
            'date' => ['required', 'date'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle_no' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],

            // Wajib hanya bila surat jalan ditarik dari pesanan penjualan.
            'items.*.sales_order_item_id' => [
                $fromOrder ? 'required' : 'nullable', 'exists:sales_order_items,id',
            ],

            // Wajib hanya pada surat jalan lepas, karena tidak ada pesanan
            // yang bisa menjadi sumber customer, gudang, dan produknya.
            'partner_id' => [$fromOrder ? 'nullable' : 'required', 'exists:partners,id'],
            'warehouse_id' => [$fromOrder ? 'nullable' : 'required', 'exists:warehouses,id'],
            'items.*.product_id' => [$fromOrder ? 'nullable' : 'required', 'integer', 'exists:products,id'],
        ]);

        $order = $fromOrder
            ? SalesOrder::with('items.product', 'customer')->findOrFail($data['sales_order_id'])
            : null;

        try {
            $delivery = DB::transaction(function () use ($data, $order) {
                $customer = $order?->customer ?? Partner::find($data['partner_id']);

                $delivery = DeliveryOrder::create([
                    'do_no' => $this->numbers->next('delivery_order', $data['date'], $customer?->initial),
                    'date' => $data['date'],
                    'sales_order_id' => $order?->id,
                    'partner_id' => $order?->partner_id ?? $data['partner_id'],
                    'warehouse_id' => $order?->warehouse_id ?? $data['warehouse_id'],
                    'driver_name' => $data['driver_name'] ?? null,
                    'vehicle_no' => $data['vehicle_no'] ?? null,
                    'shipping_address' => ($data['shipping_address'] ?? null) ?: $customer?->address,
                    'status' => 'draft',
                    'notes' => $data['notes'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                $delivery->items()->createMany(
                    $order ? $this->rowsFromOrder($order, $data['items']) : $this->rowsManual($data['items'])
                );

                return $delivery;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('delivery-orders.show', $delivery)
            ->with('success', "Surat jalan {$delivery->do_no} dibuat sebagai draft. Posting untuk mengurangi stok.");
    }

    /** Baris yang ditarik dari pesanan penjualan, dibatasi sisa yang belum dikirim. */
    private function rowsFromOrder(SalesOrder $order, array $items): array
    {
        $orderItems = $order->items->keyBy('id');
        $rows = [];

        foreach ($items as $row) {
            $quantity = round((float) $row['quantity'], 4);
            if ($quantity <= 0) {
                continue;
            }

            $orderItem = $orderItems->get((int) ($row['sales_order_item_id'] ?? 0));
            if (! $orderItem) {
                continue;
            }

            if ($quantity > $orderItem->outstandingQty()) {
                throw new RuntimeException(sprintf(
                    'Jumlah kirim untuk %s melebihi sisa pesanan (%s).',
                    $orderItem->product?->name ?? '—',
                    fnum($orderItem->outstandingQty())
                ));
            }

            $rows[] = [
                'sales_order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'quantity' => $quantity,
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('Isi minimal satu jumlah pengiriman.');
        }

        return $rows;
    }

    /** Baris bebas pada surat jalan lepas; kecukupan stok diperiksa saat posting. */
    private function rowsManual(array $items): array
    {
        $rows = [];

        foreach ($items as $row) {
            $quantity = round((float) $row['quantity'], 4);
            $productId = (int) ($row['product_id'] ?? 0);

            if ($quantity <= 0 || $productId === 0) {
                continue;
            }

            $rows[] = [
                'sales_order_item_id' => null,
                'product_id' => $productId,
                'quantity' => $quantity,
                'notes' => $row['notes'] ?? null,
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('Tambahkan minimal satu produk dengan jumlah lebih dari nol.');
        }

        return $rows;
    }

    public function show(DeliveryOrder $deliveryOrder): View
    {
        $deliveryOrder->load('items.product.uom', 'customer', 'warehouse', 'salesOrder', 'creator', 'journals');

        return view('sales.deliveries.show', ['document' => $deliveryOrder]);
    }

    public function destroy(DeliveryOrder $deliveryOrder): RedirectResponse
    {
        abort_unless($deliveryOrder->isDraft(), 403, 'Surat jalan yang sudah diposting tidak dapat dihapus.');

        $number = $deliveryOrder->do_no;
        $deliveryOrder->delete();

        return redirect()->route('delivery-orders.index')
            ->with('success', "Surat jalan {$number} berhasil dihapus.");
    }

    public function post(DeliveryOrder $deliveryOrder): RedirectResponse
    {
        try {
            $this->posting->postDelivery($deliveryOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Surat jalan {$deliveryOrder->do_no} diposting. Stok dan HPP telah dicatat.");
    }

    public function cancel(DeliveryOrder $deliveryOrder): RedirectResponse
    {
        try {
            $this->posting->cancelDelivery($deliveryOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Surat jalan {$deliveryOrder->do_no} dibatalkan dan stok dikembalikan.");
    }

    public function print(DeliveryOrder $deliveryOrder): View
    {
        $deliveryOrder->load('items.product.uom', 'customer', 'warehouse', 'salesOrder');

        return view('sales.deliveries.print', ['document' => $deliveryOrder]);
    }
}
