<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\LineItemDocumentController;
use App\Models\Master\Partner;
use App\Models\Master\Product;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use App\Services\DocumentNumberService;
use App\Services\LineItemCalculator;
use App\Services\Posting\SalesPostingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SalesInvoiceController extends LineItemDocumentController
{
    protected string $model = SalesInvoice::class;

    protected string $routeName = 'sales-invoices';

    protected string $title = 'Faktur Penjualan';

    protected string $permission = 'sales-invoice';

    protected string $numberModule = 'sales_invoice';

    protected string $numberField = 'invoice_no';

    protected string $viewPath = 'sales.invoices';

    protected array $indexWith = ['customer:id,name', 'salesOrder:id,so_no'];

    public function __construct(
        DocumentNumberService $numbers,
        LineItemCalculator $calculator,
        private readonly SalesPostingService $posting,
    ) {
        parent::__construct($numbers, $calculator);
    }

    protected function headerRules(?Model $document = null): array
    {
        return [
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'partner_id' => ['required', 'exists:partners,id'],
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function formData(?Model $document = null): array
    {
        return [
            'customers' => Partner::customers()->active()->orderBy('name')->pluck('name', 'id'),
            'openOrders' => SalesOrder::query()
                ->whereIn('status', ['confirmed', 'partial', 'delivered'])
                ->with('customer:id,name')
                ->latest('date')
                ->get(),
        ];
    }

    protected function indexData(Request $request): array
    {
        return ['customers' => Partner::customers()->orderBy('name')->pluck('name', 'id')];
    }

    protected function showWith(): array
    {
        return ['items.product.uom', 'customer', 'salesOrder', 'creator', 'journals', 'payments'];
    }

    /** Copies the un-invoiced lines of a sales order into a new invoice. */
    public function createFromOrder(SalesOrder $salesOrder): View
    {
        abort_unless($salesOrder->canInvoice(), 403, 'Pesanan ini belum dapat difakturkan.');

        $salesOrder->load('items.product.uom', 'customer.paymentTerm');

        $rows = $salesOrder->items
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

        $days = (int) ($salesOrder->customer?->paymentTerm?->days ?? 0);

        return view("{$this->viewPath}.form", array_merge([
            'document' => null,
            'sourceOrder' => $salesOrder,
            'products' => Product::optionsPayload(),
            'rows' => $rows,
            'defaultDueDate' => CarbonImmutable::now()->addDays($days)->toDateString(),
            'nextNumber' => $this->numbers->peek($this->numberModule),
            'action' => route("{$this->routeName}.store"),
            'method' => 'POST',
            'priceField' => $this->priceField,
        ], $this->formData()));
    }

    public function post(SalesInvoice $salesInvoice): RedirectResponse
    {
        try {
            $this->posting->postInvoice($salesInvoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Faktur {$salesInvoice->invoice_no} diposting ke buku besar.");
    }

    public function cancel(SalesInvoice $salesInvoice): RedirectResponse
    {
        try {
            $this->posting->cancelInvoice($salesInvoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Faktur {$salesInvoice->invoice_no} dibatalkan.");
    }

    public function print(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load('items.product.uom', 'customer', 'salesOrder');

        return view("{$this->viewPath}.print", ['document' => $salesInvoice]);
    }
}
