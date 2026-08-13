<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Concerns\LineItemDocumentController;
use App\Models\Master\Partner;
use App\Models\Master\Product;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\PurchaseOrder;
use App\Services\DocumentNumberService;
use App\Services\LineItemCalculator;
use App\Services\Posting\PurchasingPostingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PurchaseInvoiceController extends LineItemDocumentController
{
    protected string $model = PurchaseInvoice::class;

    protected string $routeName = 'purchase-invoices';

    protected string $title = 'Faktur Pembelian';

    protected string $permission = 'purchase-invoice';

    protected string $numberModule = 'purchase_invoice';

    protected string $numberField = 'invoice_no';

    protected string $viewPath = 'purchasing.invoices';

    protected string $priceField = 'purchase_price';

    protected array $indexWith = ['supplier:id,name', 'purchaseOrder:id,po_no'];

    public function __construct(
        DocumentNumberService $numbers,
        LineItemCalculator $calculator,
        private readonly PurchasingPostingService $posting,
    ) {
        parent::__construct($numbers, $calculator);
    }

    protected function headerRules(?Model $document = null): array
    {
        return [
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'partner_id' => ['required', 'exists:partners,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function formData(?Model $document = null): array
    {
        return [
            'suppliers' => Partner::suppliers()->active()->orderBy('name')->pluck('name', 'id'),
            'openOrders' => PurchaseOrder::query()
                ->whereIn('status', ['approved', 'partial', 'received'])
                ->with('supplier:id,name')
                ->latest('date')
                ->get(),
        ];
    }

    protected function indexData(Request $request): array
    {
        return ['suppliers' => Partner::suppliers()->orderBy('name')->pluck('name', 'id')];
    }

    protected function showWith(): array
    {
        return ['items.product.uom', 'supplier', 'purchaseOrder', 'creator', 'journals', 'payments'];
    }

    /** Copies the un-invoiced lines of a purchase order into a new invoice. */
    public function createFromOrder(PurchaseOrder $purchaseOrder): View
    {
        abort_unless($purchaseOrder->canInvoice(), 403, 'Pesanan ini belum dapat difakturkan.');

        $purchaseOrder->load('items.product.uom', 'supplier.paymentTerm');

        $rows = $purchaseOrder->items
            ->filter(fn ($item) => $item->uninvoicedQty() > 0)
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->uninvoicedQty(),
                'unit_price' => (float) $item->unit_price,
                'discount_percent' => (float) $item->discount_percent,
                'tax_rate' => (float) $item->tax_rate,
                'uom' => $item->product?->uom?->code ?? '',
            ])->values()->all();

        $days = (int) ($purchaseOrder->supplier?->paymentTerm?->days ?? 0);

        return view("{$this->viewPath}.form", array_merge([
            'document' => null,
            'sourceOrder' => $purchaseOrder,
            'products' => Product::optionsPayload(),
            'rows' => $rows,
            'defaultDueDate' => CarbonImmutable::now()->addDays($days)->toDateString(),
            'nextNumber' => $this->numbers->peek($this->numberModule),
            'action' => route("{$this->routeName}.store"),
            'method' => 'POST',
            'priceField' => $this->priceField,
        ], $this->formData()));
    }

    public function post(PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        try {
            $this->posting->postInvoice($purchaseInvoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Faktur {$purchaseInvoice->invoice_no} diposting ke buku besar.");
    }

    public function cancel(PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        try {
            $this->posting->cancelInvoice($purchaseInvoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Faktur {$purchaseInvoice->invoice_no} dibatalkan.");
    }

    public function print(PurchaseInvoice $purchaseInvoice): View
    {
        $purchaseInvoice->load('items.product.uom', 'supplier', 'purchaseOrder');

        return view("{$this->viewPath}.print", ['document' => $purchaseInvoice]);
    }
}
