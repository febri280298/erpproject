<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Concerns\LineItemDocumentController;
use App\Models\Master\Partner;
use App\Models\Master\PaymentTerm;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseRequisition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PurchaseOrderController extends LineItemDocumentController
{
    protected string $model = PurchaseOrder::class;

    protected string $routeName = 'purchase-orders';

    protected string $title = 'Pesanan Pembelian';

    protected string $permission = 'purchase-order';

    protected string $numberModule = 'purchase_order';

    protected string $numberField = 'po_no';

    protected string $viewPath = 'purchasing.orders';

    protected string $priceField = 'purchase_price';

    protected array $indexWith = ['supplier:id,name', 'warehouse:id,name'];

    protected function headerRules(?Model $document = null): array
    {
        return [
            'date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:date'],
            'partner_id' => ['required', 'exists:partners,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'purchase_requisition_id' => ['nullable', 'exists:purchase_requisitions,id'],
            'payment_term_id' => ['nullable', 'exists:payment_terms,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function formData(?Model $document = null): array
    {
        return [
            'suppliers' => Partner::suppliers()->active()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'paymentTerms' => PaymentTerm::active()->orderBy('days')->pluck('name', 'id'),
            'defaultWarehouse' => Warehouse::defaultId(),
        ];
    }

    protected function indexData(Request $request): array
    {
        return [
            'suppliers' => Partner::suppliers()->orderBy('name')->pluck('name', 'id'),
        ];
    }

    protected function showWith(): array
    {
        return ['items.product.uom', 'supplier', 'warehouse', 'paymentTerm', 'creator', 'approver', 'goodsReceipts', 'invoices'];
    }

    /** Pre-fills a PO from an approved purchase requisition. */
    public function createFromRequisition(PurchaseRequisition $requisition): View
    {
        abort_unless($requisition->canCreatePo(), 403, 'Permintaan pembelian belum disetujui.');

        $requisition->load('items.product.uom');

        $rows = $requisition->items
            ->filter(fn ($item) => $item->outstandingQty() > 0)
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'description' => $item->description ?: $item->product?->name,
                'quantity' => $item->outstandingQty(),
                'unit_price' => (float) ($item->unit_price ?: $item->product?->purchase_price ?? 0),
                'discount_percent' => 0,
                'tax_rate' => (float) ($item->product?->tax?->rate ?? 0),
                'uom' => $item->product?->uom?->code ?? '',
            ])->values()->all();

        return view("{$this->viewPath}.form", array_merge([
            'document' => null,
            'requisition' => $requisition,
            'products' => Product::optionsPayload(),
            'rows' => $rows,
            'nextNumber' => $this->numbers->peek($this->numberModule),
            'action' => route("{$this->routeName}.store"),
            'method' => 'POST',
            'priceField' => $this->priceField,
        ], $this->formData()));
    }

    public function approve(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->isDraft()) {
            return back()->with('error', 'Hanya pesanan berstatus draft yang dapat disetujui.');
        }

        $purchaseOrder->forceFill([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ])->save();

        $purchaseOrder->recordActivity('approved', "PO {$purchaseOrder->po_no} disetujui");

        // Mark the source requisition lines as ordered.
        if ($purchaseOrder->purchase_requisition_id) {
            $purchaseOrder->load('items');
            foreach ($purchaseOrder->items as $item) {
                $purchaseOrder->requisition?->items()
                    ->where('product_id', $item->product_id)
                    ->limit(1)
                    ->increment('ordered_qty', (float) $item->quantity);
            }
        }

        return back()->with('success', "Pesanan {$purchaseOrder->po_no} disetujui.");
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->goodsReceipts()->where('status', 'posted')->exists()) {
            return back()->with('error', 'Pesanan sudah memiliki penerimaan barang. Batalkan penerimaan terlebih dahulu.');
        }

        $purchaseOrder->forceFill(['status' => 'cancelled'])->save();
        $purchaseOrder->recordActivity('cancelled', "PO {$purchaseOrder->po_no} dibatalkan");

        return back()->with('success', "Pesanan {$purchaseOrder->po_no} dibatalkan.");
    }

    public function close(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->forceFill(['status' => 'closed'])->save();
        $purchaseOrder->recordActivity('closed', "PO {$purchaseOrder->po_no} ditutup");

        return back()->with('success', "Pesanan {$purchaseOrder->po_no} ditutup.");
    }

    public function print(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load('items.product.uom', 'supplier', 'warehouse', 'paymentTerm', 'approver');

        return view('purchasing.orders.print', ['document' => $purchaseOrder]);
    }
}
