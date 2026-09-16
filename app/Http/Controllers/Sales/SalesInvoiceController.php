<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\LineItemDocumentController;
use App\Models\Master\Partner;
use App\Models\Master\Product;
use App\Models\Master\Tax;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use App\Services\DocumentNumberService;
use App\Services\LineItemCalculator;
use App\Services\Posting\SalesPostingService;
use App\Services\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    protected bool $storesDppOther = true;

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
            'customer_po_no' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'delivery_order_ids' => ['nullable', 'array'],
            'delivery_order_ids.*' => ['integer', 'exists:delivery_orders,id'],
            'invoice_type' => ['required', Rule::in(array_keys(SalesInvoice::TYPES))],
            'wht_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function headerExcept(): array
    {
        return ['items', 'delivery_order_ids'];
    }

    /** Faktur non-PPN tidak boleh membawa pajak, apa pun isi kiriman formnya. */
    protected function prepareItems(array $items, array $data): array
    {
        if (($data['invoice_type'] ?? null) !== SalesInvoice::TYPE_NON_PPN) {
            return $items;
        }

        return array_map(fn (array $row) => array_merge($row, ['tax_rate' => 0]), $items);
    }

    /**
     * Menghitung potongan PPh 23 dari nilai jasa.
     *
     * Dasarnya adalah subtotal (di luar PPN), sesuai ketentuan PPh 23. Hanya
     * faktur bertipe Jasa yang dipotong; tipe lain dinolkan agar berganti tipe
     * tidak meninggalkan potongan lama.
     */
    private function applyWithholding(Model $document, array $data): void
    {
        $rate = ($data['invoice_type'] ?? null) === SalesInvoice::TYPE_JASA
            ? (float) ($data['wht_rate'] ?? 0)
            : 0.0;

        $document->forceFill([
            'wht_rate' => $rate,
            'wht_amount' => round((float) $document->subtotal * $rate / 100, 2),
        ])->saveQuietly();
    }

    /**
     * Menandai surat jalan terpilih sebagai sudah ditagih faktur ini.
     *
     * Penandaan dilakukan dengan syarat surat jalan tersebut masih bebas, agar
     * dua faktur yang dibuat bersamaan tidak dapat mengklaim pengiriman yang sama.
     */
    protected function afterSave(Model $document, array $data): void
    {
        $this->applyWithholding($document, $data);

        $ids = $data['delivery_order_ids'] ?? [];

        // Saat faktur draft diubah, lepaskan dulu tautan lamanya.
        DeliveryOrder::where('sales_invoice_id', $document->id)->update(['sales_invoice_id' => null]);

        if ($ids === []) {
            return;
        }

        // `uninvoiced()` sekaligus menuntut status posted: pengiriman yang masih
        // draft belum benar-benar keluar dari gudang, jadi belum boleh ditagih.
        $claimed = DeliveryOrder::query()
            ->uninvoiced()
            ->whereIn('id', $ids)
            ->where('partner_id', $document->partner_id)
            ->update(['sales_invoice_id' => $document->id]);

        if ($claimed !== count($ids)) {
            throw new RuntimeException(
                'Sebagian surat jalan tidak dapat ditagih — mungkin belum diposting, '
                .'sudah ditagih faktur lain, atau milik customer berbeda. Muat ulang halaman dan pilih kembali.'
            );
        }
    }

    protected function formData(?Model $document = null): array
    {
        return [
            'invoiceTypes' => SalesInvoice::TYPES,
            'defaultWhtRate' => (float) app(SettingService::class)->get('wht_service_rate', 2),
            'defaultTaxRate' => (float) Tax::defaultRate(),
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
        return ['items.product.uom', 'customer', 'salesOrder', 'creator', 'journals', 'payments', 'deliveryOrders'];
    }

    /**
     * Langkah 1 — pilih customer, lalu centang surat jalan yang akan ditagih.
     * Hanya menampilkan pengiriman yang sudah diposting dan belum ditagih.
     */
    /**
     * Langkah pertama: pilih sumbernya lebih dulu.
     *
     * Faktur menagih barang yang SUDAH DIKIRIM, jadi sumber yang benar adalah
     * surat jalan — bukan pesanan penjualan. Sebelumnya form ini langsung
     * terbuka kosong dengan isian "Pesanan Penjualan" di atasnya, dan isian itu
     * hanya tautan rujukan: ia tidak pernah menarik item, jumlah, maupun nomor
     * PO. Bentuknya mengundang orang menagih dari pesanan, yang berarti menagih
     * barang yang mungkin belum keluar gudang.
     *
     * Jalur manual tetap ada untuk tagihan yang memang tidak punya surat jalan
     * — jasa, ongkos pasang, penyesuaian — dan dicapai lewat ?mode=manual.
     */
    public function create(Request $request): View
    {
        return $request->query('mode') === 'manual'
            ? parent::create($request)
            : view("{$this->viewPath}.pilih-sumber");
    }

    public function selectDeliveries(Request $request): View
    {
        $partnerId = $request->query('partner_id');

        return view("{$this->viewPath}.select-deliveries", [
            'partnerId' => $partnerId,
            'customers' => Partner::customers()
                ->whereHas('salesOrders')
                ->orWhereHas('salesInvoices')
                ->orderBy('name')
                ->pluck('name', 'id'),
            'deliveries' => $partnerId
                ? DeliveryOrder::query()
                    ->uninvoiced()
                    ->where('partner_id', $partnerId)
                    ->with('items.product.uom', 'items.orderItem', 'salesOrder:id,so_no')
                    ->orderBy('date')
                    ->get()
                    ->filter(fn (DeliveryOrder $d) => $d->billableItems()->isNotEmpty())
                : collect(),
        ]);
    }

    /**
     * Langkah 2 — susun faktur dari surat jalan terpilih.
     *
     * Baris yang produk, harga, diskon, dan pajaknya sama digabung menjadi satu
     * agar faktur tetap ringkas walau menagih banyak pengiriman.
     */
    public function createFromDeliveries(Request $request): View
    {
        $data = $request->validate([
            'delivery_order_ids' => ['required', 'array', 'min:1'],
            'delivery_order_ids.*' => ['integer', 'exists:delivery_orders,id'],
        ]);

        $deliveries = DeliveryOrder::query()
            ->uninvoiced()
            ->whereIn('id', $data['delivery_order_ids'])
            ->with('items.product.uom', 'items.orderItem', 'customer.paymentTerm', 'salesOrder')
            ->orderBy('date')
            ->get();

        abort_if($deliveries->isEmpty(), 404, 'Surat jalan tidak ditemukan atau sudah ditagih.');

        if ($deliveries->pluck('partner_id')->unique()->count() > 1) {
            abort(422, 'Surat jalan yang digabung harus milik customer yang sama.');
        }

        $customer = $deliveries->first()->customer;
        $merged = [];

        foreach ($deliveries as $delivery) {
            foreach ($delivery->billableItems() as $item) {
                $price = (float) ($item->orderItem?->unit_price ?? $item->product?->sale_price ?? 0);
                $discount = (float) ($item->orderItem?->discount_percent ?? 0);
                $tax = (float) ($item->orderItem?->tax_rate ?? $item->product?->tax?->rate ?? 0);

                // Kunci gabungan: produk dengan syarat harga yang persis sama.
                $key = implode('|', [$item->product_id, $price, $discount, $tax]);

                if (! isset($merged[$key])) {
                    $merged[$key] = [
                        'product_id' => $item->product_id,
                        'description' => $item->product?->name,
                        'quantity' => 0.0,
                        'unit_price' => $price,
                        'discount_percent' => $discount,
                        'tax_rate' => $tax,
                        'uom' => $item->product?->uom?->code ?? '',
                    ];
                }

                $merged[$key]['quantity'] += $item->returnableQty();
            }
        }

        // Nomor SO diikutkan hanya bila seluruh pengiriman berasal dari satu SO.
        $orderIds = $deliveries->pluck('sales_order_id')->filter()->unique();

        // Begitu pula nomor PO customer. Satu faktur boleh menagih beberapa
        // surat jalan sekaligus, dan bila PO-nya berbeda-beda tidak ada satu
        // nomor yang benar untuk dicetak — lebih baik dikosongkan dan diisi
        // sendiri daripada menampilkan salah satu yang kebetulan terpilih.
        $poNumbers = $deliveries->pluck('customer_po_no')->filter()->unique();
        $days = (int) ($customer?->paymentTerm?->days ?? 0);

        return view("{$this->viewPath}.form", array_merge([
            'document' => null,
            'sourceDeliveries' => $deliveries,
            'lockedSalesOrderId' => $orderIds->count() === 1 ? $orderIds->first() : null,
            'lockedCustomerPoNo' => $poNumbers->count() === 1 ? $poNumbers->first() : null,
            'products' => Product::optionsPayload(),
            'rows' => array_values($merged),
            'defaultDueDate' => CarbonImmutable::now()->addDays($days)->toDateString(),
            'nextNumber' => $this->numbers->peek($this->numberModule),
            'action' => route("{$this->routeName}.store"),
            'method' => 'POST',
            'priceField' => $this->priceField,
        ], $this->formData()));
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

        // Surat jalannya kembali bebas agar dapat ditagih faktur pengganti.
        $salesInvoice->deliveryOrders()->update(['sales_invoice_id' => null]);

        return back()->with('success', "Faktur {$salesInvoice->invoice_no} dibatalkan. Surat jalan terkait dapat ditagih ulang.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $invoice = SalesInvoice::findOrFail($id);
        $invoice->deliveryOrders()->update(['sales_invoice_id' => null]);

        return parent::destroy($id);
    }

    public function print(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load('items.product.uom', 'customer', 'salesOrder');

        return view("{$this->viewPath}.print", ['document' => $salesInvoice]);
    }
}
