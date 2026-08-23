<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Controllers\Controller;
use App\Models\Master\Product;
use App\Services\DocumentNumberService;
use App\Services\LineItemCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Shared CRUD for documents made of a header plus priced line items
 * (Penawaran, Pesanan Penjualan/Pembelian, Faktur Penjualan/Pembelian).
 *
 * Subclasses supply the header validation rules and any extra view data; this
 * class owns numbering, item recalculation and the draft-only edit rule.
 */
abstract class LineItemDocumentController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $routeName;

    protected string $title;

    protected string $permission;

    /** Key in DocumentNumberService::DEFAULTS. */
    protected string $numberModule;

    /** Column holding the generated number, e.g. `po_no`. */
    protected string $numberField;

    /** Blade directory, e.g. `purchasing.orders`. */
    protected string $viewPath;

    /** Which product price seeds a new row in the browser. */
    protected string $priceField = 'sale_price';

    /**
     * Apakah tabel item dokumen ini punya kolom `dpp_other`.
     *
     * Hanya dokumen berpajak yang punya — permintaan pembelian tidak memuat
     * harga sama sekali, jadi menuliskan kolom itu ke sana akan gagal.
     */
    protected bool $storesDppOther = false;

    protected array $indexWith = [];

    protected int $perPage = 20;

    public function __construct(
        protected readonly DocumentNumberService $numbers,
        protected readonly LineItemCalculator $calculator,
    ) {}

    /** @return array<string,mixed> */
    abstract protected function headerRules(?Model $document = null): array;

    /** Extra data every create/edit form needs (partner lists, warehouses…). */
    abstract protected function formData(?Model $document = null): array;

    public function index(Request $request): View
    {
        $documents = $this->model::query()
            ->with($this->indexWith)
            ->filter($request->query())
            ->latest('date')
            ->latest('id')
            ->paginate($this->perPage)
            ->withQueryString();

        return view("{$this->viewPath}.index", array_merge(
            ['documents' => $documents],
            $this->indexData($request),
        ));
    }

    public function create(): View
    {
        return view("{$this->viewPath}.form", array_merge([
            'document' => null,
            'products' => Product::optionsPayload(),
            'rows' => [],
            'nextNumber' => $this->numbers->peek($this->numberModule),
            'action' => route("{$this->routeName}.store"),
            'method' => 'POST',
            'priceField' => $this->priceField,
        ], $this->formData()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge($this->headerRules(), $this->itemRules()));

        $document = DB::transaction(function () use ($data) {
            $rows = $this->calculator->calculate($this->prepareItems($data['items'], $data));
            $totals = $this->calculator->totals(
                $rows,
                (float) ($data['discount_amount'] ?? 0),
                (float) ($data['shipping_cost'] ?? 0),
            );

            $document = $this->model::create(array_merge(
                Arr::except($data, $this->headerExcept()),
                $totals,
                [
                    $this->numberField => $this->numbers->next(
                        $this->numberModule, $data['date'], $this->numberInitial($data)
                    ),
                    'status' => 'draft',
                    'created_by' => Auth::id(),
                ],
            ));

            $document->items()->createMany($this->mapRows($rows));
            $this->afterSave($document, $data);

            return $document;
        });

        return redirect()
            ->route("{$this->routeName}.show", $document)
            ->with('success', "{$this->title} {$document->{$this->numberField}} berhasil dibuat.");
    }

    public function show(int $id): View
    {
        $document = $this->model::with($this->showWith())->findOrFail($id);

        return view("{$this->viewPath}.show", array_merge(
            ['document' => $document],
            $this->showData($document),
        ));
    }

    public function edit(int $id): View
    {
        $document = $this->model::with('items.product')->findOrFail($id);

        abort_unless($document->isEditable(), 403, 'Dokumen yang sudah diproses tidak dapat diubah.');

        return view("{$this->viewPath}.form", array_merge([
            'document' => $document,
            'products' => Product::optionsPayload(),
            'rows' => $this->rowsPayload($document),
            'nextNumber' => $document->{$this->numberField},
            'action' => route("{$this->routeName}.update", $document),
            'method' => 'PUT',
            'priceField' => $this->priceField,
        ], $this->formData($document)));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $document = $this->model::findOrFail($id);

        abort_unless($document->isEditable(), 403, 'Dokumen yang sudah diproses tidak dapat diubah.');

        $data = $request->validate(array_merge($this->headerRules($document), $this->itemRules()));

        DB::transaction(function () use ($document, $data) {
            $rows = $this->calculator->calculate($this->prepareItems($data['items'], $data));
            $totals = $this->calculator->totals(
                $rows,
                (float) ($data['discount_amount'] ?? 0),
                (float) ($data['shipping_cost'] ?? 0),
            );

            $document->update(array_merge(Arr::except($data, $this->headerExcept()), $totals));

            // Rebuilding is safer than diffing: draft items carry no downstream state.
            $document->items()->delete();
            $document->items()->createMany($this->mapRows($rows));
            $this->afterSave($document, $data);
        });

        return redirect()
            ->route("{$this->routeName}.show", $document)
            ->with('success', "{$this->title} {$document->{$this->numberField}} berhasil diperbarui.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $document = $this->model::findOrFail($id);

        abort_unless($document->isEditable(), 403, 'Dokumen yang sudah diproses tidak dapat dihapus.');

        $number = $document->{$this->numberField};
        $document->delete();

        return redirect()
            ->route("{$this->routeName}.index")
            ->with('success', "{$this->title} {$number} berhasil dihapus.");
    }

    /** @return array<string,mixed> */
    protected function itemRules(): array
    {
        return [
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Kunci request yang bukan kolom header, sehingga tidak ikut di-mass-assign.
     * Subclass menambahkan kunci bantunya sendiri di sini.
     *
     * @return array<int,string>
     */
    protected function headerExcept(): array
    {
        return ['items'];
    }

    /**
     * Hook: menyesuaikan baris sebelum dihitung, mis. memaksa tarif pajak nol
     * pada dokumen non-PPN. Wajib di sisi server karena aturan di peramban
     * dapat dilewati dengan mengirim form secara langsung.
     *
     * @param  array<int,array<string,mixed>>  $items
     * @return array<int,array<string,mixed>>
     */
    protected function prepareItems(array $items, array $data): array
    {
        return $items;
    }

    /** Hook: dijalankan di dalam transaksi setelah item tersimpan. */
    protected function afterSave(Model $document, array $data): void {}

    /** Strip anything the item table has no column for. */
    /**
     * Inisial mitra yang disisipkan ke nomor dokumen, bila modulnya memakai.
     *
     * Bawaannya null: kebanyakan dokumen tidak menyisipkannya, dan yang memakai
     * cukup menimpa method ini.
     *
     * @param  array<string,mixed>  $data
     */
    protected function numberInitial(array $data): ?string
    {
        return null;
    }

    protected function mapRows(array $rows): array
    {
        $columns = [
            'product_id', 'description', 'quantity', 'unit_price',
            'discount_percent', 'tax_rate', 'tax_amount', 'subtotal', 'total',
        ];

        // Hanya dokumen berpajak yang punya kolomnya; permintaan pembelian
        // tidak, jadi kolomnya tidak boleh ikut disertakan di sana.
        if ($this->storesDppOther) {
            $columns[] = 'dpp_other';
        }

        return array_map(fn (array $row) => Arr::only($row, $columns), $rows);
    }

    /** Shape stored items the way resources/js/doc-items.js expects them. */
    protected function rowsPayload(Model $document): array
    {
        return $document->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'discount_percent' => (float) $item->discount_percent,
            'tax_rate' => (float) $item->tax_rate,
            'uom' => $item->product?->uom?->code ?? '',
        ])->all();
    }

    protected function showWith(): array
    {
        return ['items.product.uom'];
    }

    /** @return array<string,mixed> */
    protected function indexData(Request $request): array
    {
        return [];
    }

    /** @return array<string,mixed> */
    protected function showData(Model $document): array
    {
        return [];
    }
}
