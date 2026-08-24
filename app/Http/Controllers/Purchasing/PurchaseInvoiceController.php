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

    protected bool $storesDppOther = true;

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
            'purchase_order_ids' => ['nullable', 'array'],
            'purchase_order_ids.*' => ['integer', 'exists:purchase_orders,id'],
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

    protected function headerExcept(): array
    {
        return [...parent::headerExcept(), 'purchase_order_ids'];
    }

    /**
     * Menautkan faktur ke pesanan-pesanan yang ditagihnya.
     *
     * sync() dipakai, bukan attach(): faktur draft bisa disunting ulang dan
     * pilihannya berubah, sehingga tautan lama harus ikut terhapus.
     */
    protected function afterSave(Model $document, array $data): void
    {
        $ids = $data['purchase_order_ids'] ?? [];

        // Faktur dari satu PO lewat jalur lama tetap tercatat di tabel
        // penghubung, supaya penelusuran asal faktur cukup melihat satu tempat.
        if ($ids === [] && ! empty($data['purchase_order_id'])) {
            $ids = [$data['purchase_order_id']];
        }

        $document->purchaseOrders()->sync($ids);
    }

    protected function showWith(): array
    {
        return ['items.product.uom', 'supplier', 'purchaseOrder', 'purchaseOrders:id,po_no',
            'creator', 'journals', 'payments'];
    }

    /** Copies the un-invoiced lines of a purchase order into a new invoice. */
    /**
     * Langkah 1 — pilih supplier, lalu centang pesanan yang mau ditagih.
     *
     * Supplier kerap mengirim satu tagihan untuk beberapa PO sekaligus, dan
     * memaksa satu faktur per PO membuat pembukuan tidak cocok dengan kertas
     * yang diterima.
     */
    public function selectOrders(Request $request): View
    {
        $partnerId = $request->query('partner_id');

        return view("{$this->viewPath}.pilih-pesanan", [
            'partnerId' => $partnerId,
            'suppliers' => Partner::suppliers()
                ->whereHas('purchaseOrders')
                ->orderBy('name')
                ->pluck('name', 'id'),
            'orders' => $partnerId
                ? PurchaseOrder::query()
                    ->where('partner_id', $partnerId)
                    ->whereIn('status', ['approved', 'partial', 'received'])
                    ->with('items.product.uom')
                    ->orderBy('date')
                    ->get()
                    // Hanya yang masih menyisakan kuantitas belum ditagih.
                    ->filter(fn (PurchaseOrder $o) => $o->items->sum(fn ($i) => $i->uninvoicedQty()) > 0)
                    ->values()
                : collect(),
        ]);
    }

    /**
     * Langkah 2 — susun satu faktur dari pesanan yang dipilih.
     *
     * Baris digabung per produk BESERTA syarat harganya. Produk sama dengan
     * harga atau diskon berbeda tetap jadi baris terpisah: menggabungkannya
     * akan mengubah nilai tagihan, bukan sekadar merapikan tampilan.
     */
    public function createFromOrders(Request $request): View
    {
        $data = $request->validate([
            'purchase_order_ids' => ['required', 'array', 'min:1'],
            'purchase_order_ids.*' => ['integer', 'exists:purchase_orders,id'],
        ]);

        $orders = PurchaseOrder::query()
            ->whereIn('id', $data['purchase_order_ids'])
            ->whereIn('status', ['approved', 'partial', 'received'])
            ->with('items.product.uom', 'supplier.paymentTerm')
            ->orderBy('date')
            ->get();

        abort_if($orders->isEmpty(), 404, 'Pesanan pembelian tidak ditemukan atau sudah ditagih.');

        if ($orders->pluck('partner_id')->unique()->count() > 1) {
            abort(422, 'Pesanan yang digabung harus milik supplier yang sama.');
        }

        $merged = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $sisa = $item->uninvoicedQty();

                if ($sisa <= 0) {
                    continue;
                }

                $kunci = implode('|', [
                    $item->product_id,
                    (float) $item->unit_price,
                    (float) $item->discount_percent,
                    (float) $item->tax_rate,
                ]);

                if (! isset($merged[$kunci])) {
                    $merged[$kunci] = [
                        'product_id' => $item->product_id,
                        'description' => $item->description ?: $item->product?->name,
                        'quantity' => 0.0,
                        'unit_price' => (float) $item->unit_price,
                        'discount_percent' => (float) $item->discount_percent,
                        'tax_rate' => (float) $item->tax_rate,
                        'uom' => $item->product?->uom?->code ?? '',
                    ];
                }

                $merged[$kunci]['quantity'] += $sisa;
            }
        }

        $supplier = $orders->first()->supplier;
        $days = (int) ($supplier?->paymentTerm?->days ?? 0);

        return view("{$this->viewPath}.form", array_merge([
            'document' => null,
            'sourceOrders' => $orders,
            'products' => Product::optionsPayload(),
            'rows' => array_values($merged),
            'defaultDueDate' => CarbonImmutable::now()->addDays($days)->toDateString(),
            'nextNumber' => $this->numbers->peek($this->numberModule),
            'action' => route("{$this->routeName}.store"),
            'method' => 'POST',
            'priceField' => $this->priceField,
        ], $this->formData()));
    }

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
