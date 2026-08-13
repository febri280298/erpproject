<?php

namespace App\Http\Controllers\Master;

use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Imports\ProductsImport;
use App\Models\Inventory\StockMovement;
use App\Models\Master\Product;
use App\Models\Master\ProductCategory;
use App\Models\Master\Tax;
use App\Models\Master\Uom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with(['category:id,name', 'uom:id,code'])
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

        $product = Product::create($data);

        return redirect()
            ->route('products.show', $product)
            ->with('success', "Produk \"{$product->name}\" berhasil ditambahkan.");
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'uom', 'tax', 'stocks.warehouse']);

        return view('master.products.show', [
            'product' => $product,
            'movements' => StockMovement::query()
                ->with('warehouse:id,name')
                ->where('product_id', $product->id)
                ->latest('date')
                ->latest('id')
                ->limit(25)
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

        $product->update($data);

        return redirect()
            ->route('products.show', $product)
            ->with('success', "Produk \"{$product->name}\" berhasil diperbarui.");
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
        return [
            'product' => $product,
            'categories' => ProductCategory::active()->orderBy('name')->pluck('name', 'id'),
            'uoms' => Uom::active()->orderBy('code')->pluck('name', 'id'),
            'taxes' => Tax::active()->orderBy('code')->get()
                ->mapWithKeys(fn (Tax $t) => [$t->id => $t->name.' ('.fnum($t->rate).'%)']),
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
        ]);

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
