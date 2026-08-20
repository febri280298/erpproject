<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\LineItemDocumentController;
use App\Models\Master\Partner;
use App\Models\Master\PaymentTerm;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SalesOrderController extends LineItemDocumentController
{
    protected string $model = SalesOrder::class;

    protected string $routeName = 'sales-orders';

    protected string $title = 'Pesanan Penjualan';

    protected string $permission = 'sales-order';

    protected string $numberModule = 'sales_order';

    protected string $numberField = 'so_no';

    protected string $viewPath = 'sales.orders';

    protected bool $storesDppOther = true;

    protected array $indexWith = ['customer:id,name', 'warehouse:id,name'];

    protected function headerRules(?Model $document = null): array
    {
        return [
            'date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:date'],
            'partner_id' => ['required', 'exists:partners,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quotation_id' => ['nullable', 'exists:quotations,id'],
            'payment_term_id' => ['nullable', 'exists:payment_terms,id'],
            'customer_po_no' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function formData(?Model $document = null): array
    {
        return [
            'customers' => Partner::customers()->active()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'paymentTerms' => PaymentTerm::active()->orderBy('days')->pluck('name', 'id'),
            'defaultWarehouse' => Warehouse::defaultId(),
        ];
    }

    protected function indexData(Request $request): array
    {
        return ['customers' => Partner::customers()->orderBy('name')->pluck('name', 'id')];
    }

    protected function showWith(): array
    {
        return ['items.product.uom', 'customer', 'warehouse', 'paymentTerm', 'creator', 'approver', 'deliveryOrders', 'invoices'];
    }

    /** Converts an accepted quotation into a sales order. */
    public function createFromQuotation(Quotation $quotation): View
    {
        abort_unless($quotation->canConvert(), 403, 'Penawaran ini belum diterima atau sudah dikonversi.');

        $quotation->load('items.product.uom');

        $rows = $quotation->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'discount_percent' => (float) $item->discount_percent,
            'tax_rate' => (float) $item->tax_rate,
            'uom' => $item->product?->uom?->code ?? '',
        ])->all();

        return view("{$this->viewPath}.form", array_merge([
            'document' => null,
            'sourceQuotation' => $quotation,
            'products' => Product::optionsPayload(),
            'rows' => $rows,
            'nextNumber' => $this->numbers->peek($this->numberModule),
            'action' => route("{$this->routeName}.store"),
            'method' => 'POST',
            'priceField' => $this->priceField,
        ], $this->formData()));
    }

    public function approve(SalesOrder $salesOrder): RedirectResponse
    {
        if (! $salesOrder->isDraft()) {
            return back()->with('error', 'Hanya pesanan berstatus draft yang dapat dikonfirmasi.');
        }

        $salesOrder->forceFill([
            'status' => 'confirmed',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ])->save();

        $salesOrder->recordActivity('confirmed', "SO {$salesOrder->so_no} dikonfirmasi");

        if ($salesOrder->quotation_id) {
            $salesOrder->quotation?->forceFill(['status' => 'closed'])->save();
        }

        return back()->with('success', "Pesanan {$salesOrder->so_no} dikonfirmasi.");
    }

    public function cancel(SalesOrder $salesOrder): RedirectResponse
    {
        if ($salesOrder->deliveryOrders()->where('status', 'posted')->exists()) {
            return back()->with('error', 'Pesanan sudah memiliki pengiriman. Batalkan surat jalan terlebih dahulu.');
        }

        $salesOrder->forceFill(['status' => 'cancelled'])->save();
        $salesOrder->recordActivity('cancelled', "SO {$salesOrder->so_no} dibatalkan");

        return back()->with('success', "Pesanan {$salesOrder->so_no} dibatalkan.");
    }

    public function close(SalesOrder $salesOrder): RedirectResponse
    {
        $salesOrder->forceFill(['status' => 'closed'])->save();
        $salesOrder->recordActivity('closed', "SO {$salesOrder->so_no} ditutup");

        return back()->with('success', "Pesanan {$salesOrder->so_no} ditutup.");
    }

    public function print(SalesOrder $salesOrder): View
    {
        $salesOrder->load('items.product.uom', 'customer', 'warehouse', 'paymentTerm');

        return view("{$this->viewPath}.print", ['document' => $salesOrder]);
    }
}
