<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Master\Partner;
use App\Models\Master\Warehouse;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Services\DocumentNumberService;
use App\Services\LineItemCalculator;
use App\Services\Posting\SalesPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Retur penjualan selalu bertolak dari surat jalan yang sudah diposting: dari
 * sanalah jumlah maksimal yang boleh dikembalikan dan harga jual aslinya
 * diketahui, sehingga nota kreditnya tidak mengarang angka.
 */
class SalesReturnController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly LineItemCalculator $calculator,
        private readonly SalesPostingService $posting,
    ) {}

    public function index(Request $request): View
    {
        return view('sales.returns.index', [
            'documents' => SalesReturn::query()
                ->with(['customer:id,name', 'warehouse:id,name', 'deliveryOrder:id,do_no', 'salesInvoice:id,invoice_no'])
                ->withCount('items')
                ->filter($request->query())
                ->latest('date')->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'customers' => Partner::customers()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        $delivery = $request->filled('delivery_order_id')
            ? DeliveryOrder::with([
                'items.product.uom', 'items.orderItem', 'customer', 'warehouse',
                'salesOrder.invoices',
            ])->find($request->query('delivery_order_id'))
            : null;

        if ($delivery && ! $delivery->canReturn()) {
            abort(403, 'Surat jalan ini belum diposting atau seluruh barangnya sudah diretur.');
        }

        return view('sales.returns.form', [
            'delivery' => $delivery,
            'openDeliveries' => DeliveryOrder::query()
                ->where('status', 'posted')
                ->with('customer:id,name')
                ->latest('date')
                ->limit(100)
                ->get()
                ->filter(fn (DeliveryOrder $d) => $d->canReturn())
                ->values(),
            // Faktur yang masih bisa dikurangi nota kredit.
            'invoices' => $delivery
                ? $delivery->salesOrder?->invoices()->whereIn('status', ['posted', 'partial', 'paid'])->get() ?? collect()
                : collect(),
            'rows' => $delivery ? $this->rowsFromDelivery($delivery) : [],
            'nextNumber' => $this->numbers->peek('sales_return'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'delivery_order_id' => ['required', 'exists:delivery_orders,id'],
            'sales_invoice_id' => ['nullable', 'exists:sales_invoices,id'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'issue_credit_note' => ['boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.delivery_order_item_id' => ['required', 'exists:delivery_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.condition' => ['required', 'in:good,damaged'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $delivery = DeliveryOrder::with('items.product', 'customer')->findOrFail($data['delivery_order_id']);

        try {
            $return = DB::transaction(function () use ($data, $delivery) {
                $deliveryItems = $delivery->items->keyBy('id');
                $rows = [];

                foreach ($data['items'] as $row) {
                    $quantity = round((float) $row['quantity'], 4);
                    if ($quantity <= 0) {
                        continue;
                    }

                    $source = $deliveryItems->get((int) $row['delivery_order_item_id']);
                    if (! $source) {
                        continue;
                    }

                    if ($quantity > $source->returnableQty()) {
                        throw new RuntimeException(sprintf(
                            'Jumlah retur %s melebihi yang masih bisa dikembalikan (%s).',
                            $source->product?->name ?? '—',
                            fnum($source->returnableQty())
                        ));
                    }

                    $rows[] = [
                        'delivery_order_item_id' => $source->id,
                        'product_id' => $source->product_id,
                        'condition' => $row['condition'],
                        'notes' => $row['notes'] ?? null,
                        'quantity' => $quantity,
                        'unit_price' => $row['unit_price'],
                        'discount_percent' => $row['discount_percent'] ?? 0,
                        'tax_rate' => $row['tax_rate'] ?? 0,
                    ];
                }

                if ($rows === []) {
                    throw new RuntimeException('Isi minimal satu jumlah retur.');
                }

                $rows = $this->calculator->calculate($rows);
                $totals = $this->calculator->totals($rows);

                $return = SalesReturn::create([
                    'return_no' => $this->numbers->next('sales_return', $data['date']),
                    'date' => $data['date'],
                    'delivery_order_id' => $delivery->id,
                    'sales_invoice_id' => $data['sales_invoice_id'] ?? null,
                    'partner_id' => $delivery->partner_id,
                    'warehouse_id' => $delivery->warehouse_id,
                    'reason' => $data['reason'] ?? null,
                    'subtotal' => $totals['subtotal'],
                    'tax_amount' => $totals['tax_amount'],
                    'total' => $totals['total'],
                    'issue_credit_note' => ($data['issue_credit_note'] ?? false) && ! empty($data['sales_invoice_id']),
                    'status' => 'draft',
                    'notes' => $data['notes'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                $return->items()->createMany($rows);

                return $return;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('sales-returns.show', $return)
            ->with('success', "Retur {$return->return_no} dibuat sebagai draft. Posting untuk mengembalikan stok.");
    }

    public function show(SalesReturn $salesReturn): View
    {
        $salesReturn->load([
            'items.product.uom', 'customer', 'warehouse',
            'deliveryOrder', 'salesInvoice', 'creator', 'journals',
        ]);

        return view('sales.returns.show', ['document' => $salesReturn]);
    }

    public function destroy(SalesReturn $salesReturn): RedirectResponse
    {
        abort_unless($salesReturn->isDraft(), 403, 'Retur yang sudah diposting tidak dapat dihapus.');

        $number = $salesReturn->return_no;
        $salesReturn->delete();

        return redirect()->route('sales-returns.index')
            ->with('success', "Retur {$number} berhasil dihapus.");
    }

    public function post(SalesReturn $salesReturn): RedirectResponse
    {
        try {
            $this->posting->postReturn($salesReturn);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Retur {$salesReturn->return_no} diposting. Stok dan pembukuan telah diperbarui.");
    }

    public function cancel(SalesReturn $salesReturn): RedirectResponse
    {
        try {
            $this->posting->cancelReturn($salesReturn);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Retur {$salesReturn->return_no} dibatalkan.");
    }

    public function print(SalesReturn $salesReturn): View
    {
        $salesReturn->load('items.product.uom', 'customer', 'warehouse', 'deliveryOrder');

        return view('sales.returns.print', ['document' => $salesReturn]);
    }

    /**
     * Baris awal form: sisa yang masih bisa diretur, berikut harga jual dari
     * pesanan penjualan asalnya agar nota kredit memakai harga yang ditagihkan.
     *
     * @return array<int,array<string,mixed>>
     */
    private function rowsFromDelivery(DeliveryOrder $delivery): array
    {
        return $delivery->items
            ->filter(fn ($item) => $item->returnableQty() > 0)
            ->map(fn ($item) => [
                'delivery_order_item_id' => $item->id,
                'product' => $item->product,
                'delivered' => (float) $item->quantity,
                'returned' => (float) $item->returned_qty,
                'returnable' => $item->returnableQty(),
                'unit_price' => (float) ($item->orderItem?->unit_price ?? $item->product?->sale_price ?? 0),
                'discount_percent' => (float) ($item->orderItem?->discount_percent ?? 0),
                'tax_rate' => (float) ($item->orderItem?->tax_rate ?? 0),
                'condition' => SalesReturnItem::GOOD,
            ])
            ->values()
            ->all();
    }
}
