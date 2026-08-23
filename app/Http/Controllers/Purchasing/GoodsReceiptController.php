<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Master\Partner;
use App\Models\Master\Warehouse;
use App\Models\Purchasing\GoodsReceipt;
use App\Models\Purchasing\PurchaseOrder;
use App\Services\DocumentNumberService;
use App\Services\Posting\PurchasingPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Goods receipts are almost always created against an approved purchase order,
 * so the form is driven by the PO's outstanding lines rather than a free list.
 */
class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly PurchasingPostingService $posting,
    ) {}

    public function index(Request $request): View
    {
        return view('purchasing.receipts.index', [
            'documents' => GoodsReceipt::query()
                ->with(['supplier:id,name', 'warehouse:id,name', 'purchaseOrder:id,po_no'])
                ->filter($request->query())
                ->latest('date')->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'suppliers' => Partner::suppliers()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Step 1 — pick the purchase order to receive against. */
    public function create(Request $request): View
    {
        $order = $request->filled('purchase_order_id')
            ? PurchaseOrder::with('items.product.uom', 'supplier', 'warehouse')->find($request->query('purchase_order_id'))
            : null;

        if ($order && ! $order->canReceive()) {
            abort(403, 'Pesanan ini tidak memiliki item yang menunggu penerimaan.');
        }

        return view('purchasing.receipts.form', [
            'order' => $order,
            'openOrders' => PurchaseOrder::query()
                ->whereIn('status', ['approved', 'partial'])
                ->with('supplier:id,name')
                ->latest('date')
                ->get(),
            'nextNumber' => $this->numbers->peek('goods_receipt'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'date' => ['required', 'date'],
            'supplier_do_no' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $order = PurchaseOrder::with('items')->findOrFail($data['purchase_order_id']);

        $receipt = DB::transaction(function () use ($data, $order) {
            $receipt = GoodsReceipt::create([
                'grn_no' => $this->numbers->next('goods_receipt', $data['date'], $order->supplier?->initial),
                'date' => $data['date'],
                'purchase_order_id' => $order->id,
                'partner_id' => $order->partner_id,
                'warehouse_id' => $order->warehouse_id,
                'supplier_do_no' => $data['supplier_do_no'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $orderItems = $order->items->keyBy('id');
            $rows = [];

            foreach ($data['items'] as $row) {
                $quantity = round((float) $row['quantity'], 4);
                if ($quantity <= 0) {
                    continue;
                }

                $orderItem = $orderItems->get((int) $row['purchase_order_item_id']);
                if (! $orderItem) {
                    continue;
                }

                if ($quantity > $orderItem->outstandingQty()) {
                    throw new RuntimeException(sprintf(
                        'Jumlah terima untuk %s melebihi sisa pesanan (%s).',
                        $orderItem->product?->name ?? '—',
                        fnum($orderItem->outstandingQty())
                    ));
                }

                $rows[] = [
                    'purchase_order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'quantity' => $quantity,
                    'unit_price' => (float) $orderItem->unit_price,
                ];
            }

            if ($rows === []) {
                throw new RuntimeException('Isi minimal satu jumlah penerimaan.');
            }

            $receipt->items()->createMany($rows);

            return $receipt;
        });

        return redirect()
            ->route('goods-receipts.show', $receipt)
            ->with('success', "Penerimaan {$receipt->grn_no} dibuat sebagai draft. Posting untuk menambahkan ke stok.");
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        $goodsReceipt->load('items.product.uom', 'supplier', 'warehouse', 'purchaseOrder', 'creator', 'journals');

        return view('purchasing.receipts.show', ['document' => $goodsReceipt]);
    }

    public function destroy(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        abort_unless($goodsReceipt->isDraft(), 403, 'Penerimaan yang sudah diposting tidak dapat dihapus.');

        $number = $goodsReceipt->grn_no;
        $goodsReceipt->delete();

        return redirect()->route('goods-receipts.index')
            ->with('success', "Penerimaan {$number} berhasil dihapus.");
    }

    public function post(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        try {
            $this->posting->postGoodsReceipt($goodsReceipt);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Penerimaan {$goodsReceipt->grn_no} diposting. Stok dan jurnal telah diperbarui.");
    }

    public function cancel(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        try {
            $this->posting->cancelGoodsReceipt($goodsReceipt);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Penerimaan {$goodsReceipt->grn_no} dibatalkan dan stok dikembalikan.");
    }

    public function print(GoodsReceipt $goodsReceipt): View
    {
        $goodsReceipt->load('items.product.uom', 'supplier', 'warehouse', 'purchaseOrder');

        return view('purchasing.receipts.print', ['document' => $goodsReceipt]);
    }
}
