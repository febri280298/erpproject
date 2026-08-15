<?php

namespace App\Http\Controllers\Master;

use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Imports\ProductsImport;
use App\Models\Inventory\StockMovement;
use App\Models\Master\Partner;
use App\Models\Master\PriceLevel;
use App\Models\Master\Product;
use App\Models\Master\ProductCategory;
use App\Models\Master\Tax;
use App\Models\Master\Uom;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    public function __construct(private readonly PricingService $pricing) {}

    public function index(Request $request): View
    {
        $products = Product::query()
            ->with(['category:id,name', 'uom:id,code', 'tax:id,name,rate'])
            ->withSum('stocks as on_hand', 'quantity')
            ->search($request->query('q'))
            ->when($request->filled('category'), fn ($q) => $q->where('product_category_id', $request->query('category')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->query('status') === 'active'))
            ->when($request->query('low') === '1', fn ($q) => $q
                ->where('type', Product::TYPE_STOCK)
                ->where('min_stock', '>', 0)
                ->havingRaw('COALESCE(on_hand, 0) < products.min_stock'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('master.products.index', [
            'products' => $products,
            'categories' => ProductCategory::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('master.products.form', $this->formData(new Product([
            'type' => Product::TYPE_STOCK,
            'is_active' => true,
            'tax_id' => Tax::where('is_default', true)->value('id'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['image'] = $this->storeImage($request);

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);
            $this->savePrices($product, $request);

            return $product;
        });

        return redirect()
            ->route('products.show', $product)
            ->with('success', "Produk \"{$product->name}\" berhasil ditambahkan.");
    }

    public function show(Product $product): View
    {
        $product->load([
            'category', 'uom', 'tax', 'stocks.warehouse',
            'prices.level', 'supplierPrices.supplier', 'customerPrices.customer',
        ]);

        return view('master.products.show', [
            'product' => $product,
            'movements' => StockMovement::query()
                ->with('warehouse:id,name')
                ->where('product_id', $product->id)
                ->latest('date')
                ->latest('id')
                ->limit(25)
                ->get(),
            'histories' => $product->priceHistories()
                ->with('changer:id,name', 'partner:id,name', 'level:id,name')
                ->limit(30)
                ->get(),
        ]);
    }

    public function edit(Product $product): View
    {
        return view('master.products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);

        if ($image = $this->storeImage($request)) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $image;
        }

        $changed = DB::transaction(function () use ($product, $data, $request) {
            $product->update($data);

            return $this->savePrices($product, $request);
        });

        $note = $changed > 0 ? " {$changed} perubahan harga dicatat di riwayat." : '';

        return redirect()
            ->route('products.show', $product)
            ->with('success', "Produk \"{$product->name}\" berhasil diperbarui.{$note}");
    }

    /**
     * Writes both price tables through PricingService, which is what records
     * the history rows.
     */
    private function savePrices(Product $product, Request $request): int
    {
        $changed = $this->pricing->syncSalePrices($product, $request->input('prices', []));
        $changed += $this->pricing->syncCustomerPrices($product, $request->input('customer_prices', []));
        $changed += $this->pricing->syncSupplierPrices($product, $request->input('supplier_prices', []));

        return $changed;
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->movements()->exists()) {
            return back()->with('error', 'Produk ini sudah memiliki riwayat transaksi dan tidak dapat dihapus. Nonaktifkan saja.');
        }

        $name = $product->name;
        $product->delete();

        return redirect()->route('products.index')->with('success', "Produk \"{$name}\" berhasil dihapus.");
    }

    public function importForm(): View
    {
        return view('master.products.import', [
            'categories' => ProductCategory::active()->orderBy('name')->pluck('name', 'code'),
            'uoms' => Uom::active()->orderBy('code')->pluck('name', 'code'),
            'taxes' => Tax::active()->orderBy('code')->get()
                ->mapWithKeys(fn (Tax $t) => [$t->code => $t->name.' ('.fnum($t->rate).'%)']),
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new ProductsExport(templateOnly: true), 'template-produk.xlsx');
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new ProductsExport, 'data-produk-'.now()->format('Ymd-His').'.xlsx');
    }

    /**
     * Reads the uploaded sheet row by row: valid rows are saved, invalid ones
     * come back as a per-row report so the user can fix just those lines.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'update_existing' => ['boolean'],
            'create_missing_refs' => ['boolean'],
        ], [], ['file' => 'Berkas']);

        $importer = new ProductsImport(
            updateExisting: $request->boolean('update_existing'),
            createMissingRefs: $request->boolean('create_missing_refs'),
        );

        try {
            Excel::import($importer, $request->file('file'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Berkas tidak dapat dibaca: '.$e->getMessage());
        }

        if ($importer->total() === 0 && $importer->errors === [] && $importer->skipped === 0) {
            return back()->with('error', 'Tidak ada baris data yang terbaca. Pastikan baris pertama berisi nama kolom.');
        }

        return back()
            ->with('import_result', [
                'created' => $importer->created,
                'updated' => $importer->updated,
                'skipped' => $importer->skipped,
                'errors' => $importer->errors,
            ])
            ->with($importer->errors === [] ? 'success' : 'warning', sprintf(
                '%d produk baru, %d diperbarui, %d dilewati, %d gagal.',
                $importer->created,
                $importer->updated,
                $importer->skipped,
                count($importer->errors),
            ));
    }

    /** JSON feed for the product picker used across document forms. */
    public function search(Request $request)
    {
        return response()->json(
            Product::query()
                ->active()
                ->search($request->query('q'))
                ->with('uom:id,code')
                ->limit(20)
                ->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'label' => $p->label(),
                    'uom' => $p->uom?->code,
                    'sale_price' => (float) $p->sale_price,
                    'purchase_price' => (float) $p->purchase_price,
                ])
        );
    }

    private function formData(Product $product): array
    {
        if ($product->exists) {
            $product->loadMissing('prices', 'supplierPrices', 'customerPrices');
        } else {
            $product->setRelation('prices', collect())
                ->setRelation('supplierPrices', collect())
                ->setRelation('customerPrices', collect());
        }

        // Partner pickers are `{id, label}` lists so Alpine can filter out rows
        // already added without another round trip.
        $asOptions = fn ($partners) => $partners
            ->map(fn (Partner $p) => ['id' => $p->id, 'label' => $p->code.' — '.$p->name])
            ->values()
            ->all();

        return [
            'product' => $product,
            'categories' => ProductCategory::active()->orderBy('name')->pluck('name', 'id'),
            'uoms' => Uom::active()->orderBy('code')->pluck('name', 'id'),
            // Label menyebut perlakuannya, bukan hanya nama pajaknya, agar
            // terlihat jelas mana produk kena PPN dan mana yang bebas.
            'taxes' => Tax::active()->orderBy('rate')->get()
                ->mapWithKeys(fn (Tax $t) => [
                    $t->id => ((float) $t->rate > 0 ? 'Kena PPN' : 'Bebas PPN')
                        .' — '.$t->name.' ('.fnum($t->rate).'%)',
                ]),
            'priceLevels' => PriceLevel::active()->ordered()->get(),
            // Keyed so the form can look a stored tier price up by level id.
            'salePrices' => $product->prices->keyBy('price_level_id'),

            'supplierOptions' => $asOptions(Partner::suppliers()->active()->orderBy('name')->get(['id', 'code', 'name'])),
            'customerOptions' => $asOptions(Partner::customers()->active()->orderBy('name')->get(['id', 'code', 'name'])),

            'supplierRowsPayload' => $product->supplierPrices->map(fn ($r) => [
                'partner_id' => $r->partner_id,
                'price' => (float) $r->price,
                'supplier_sku' => $r->supplier_sku,
                'lead_time_days' => (int) $r->lead_time_days,
                'min_order_qty' => (float) $r->min_order_qty,
                'last_purchased_at' => $r->last_purchased_at?->translatedFormat('d M Y'),
            ])->values()->all(),

            'customerRowsPayload' => $product->customerPrices->map(fn ($r) => [
                'partner_id' => $r->partner_id,
                'price' => (float) $r->price,
                'min_qty' => (float) $r->min_qty,
                'notes' => $r->notes,
            ])->values()->all(),

            'preferredSupplierId' => $product->supplierPrices->firstWhere('is_preferred', true)?->partner_id,
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($product?->id)],
            'barcode' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in([Product::TYPE_STOCK, Product::TYPE_SERVICE])],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'uom_id' => ['nullable', 'exists:uoms,id'],
            'tax_id' => ['nullable', 'exists:taxes,id'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:2048'],

            'prices' => ['nullable', 'array', 'max:'.PriceLevel::MAX],
            'prices.*.price_level_id' => ['required', 'integer', 'exists:price_levels,id'],
            'prices.*.price' => ['nullable', 'numeric', 'min:0'],
            'prices.*.min_qty' => ['nullable', 'numeric', 'min:0'],

            'supplier_prices' => ['nullable', 'array', 'max:20'],
            'supplier_prices.*.partner_id' => ['required', 'integer', 'exists:partners,id'],
            'supplier_prices.*.price' => ['nullable', 'numeric', 'min:0'],
            'supplier_prices.*.supplier_sku' => ['nullable', 'string', 'max:60'],
            'supplier_prices.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'supplier_prices.*.min_order_qty' => ['nullable', 'numeric', 'min:0'],
            'supplier_prices.*.is_preferred' => ['nullable', 'boolean'],
            'supplier_prices.*.notes' => ['nullable', 'string', 'max:255'],

            'customer_prices' => ['nullable', 'array', 'max:100'],
            'customer_prices.*.partner_id' => ['required', 'integer', 'exists:partners,id'],
            'customer_prices.*.price' => ['nullable', 'numeric', 'min:0'],
            'customer_prices.*.min_qty' => ['nullable', 'numeric', 'min:0'],
            'customer_prices.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        // Price tables are written separately by PricingService.
        unset($data['prices'], $data['supplier_prices'], $data['customer_prices']);

        // The uploaded file is handled separately; never mass-assign it.
        unset($data['image']);

        return $data;
    }

    private function storeImage(Request $request): ?string
    {
        return $request->hasFile('image')
            ? $request->file('image')->store('products', 'public')
            : null;
    }
}
