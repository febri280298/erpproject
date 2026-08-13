<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockMovement;
use App\Models\Master\Product;
use App\Models\Master\ProductCategory;
use App\Models\Master\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $stocks = Stock::query()
            ->with(['product:id,sku,name,uom_id,min_stock,product_category_id', 'product.uom:id,code', 'warehouse:id,name'])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->query('warehouse_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas('product', fn ($w) => $w->where('product_category_id', $request->query('category_id'))))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('product', fn ($w) => $w->search($request->query('q'))))
            ->when($request->query('low') === '1', fn ($q) => $q->whereHas('product', fn ($w) => $w->whereColumn('products.min_stock', '>', 'stocks.quantity')->where('min_stock', '>', 0)))
            ->when($request->query('nonzero') === '1', fn ($q) => $q->where('quantity', '!=', 0))
            ->orderByDesc('quantity')
            ->paginate(25)
            ->withQueryString();

        $summary = Stock::query()
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->query('warehouse_id')))
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_qty, COALESCE(SUM(quantity * avg_cost), 0) as total_value')
            ->first();

        return view('inventory.stocks.index', [
            'stocks' => $stocks,
            'summary' => $summary,
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'categories' => ProductCategory::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Stock card: opening balance + every movement in the period. */
    public function card(Request $request): View
    {
        $productId = $request->query('product_id');
        $warehouseId = $request->query('warehouse_id');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $movements = collect();
        $opening = ['quantity' => 0.0, 'value' => 0.0];

        if ($productId) {
            $base = StockMovement::query()
                ->where('product_id', $productId)
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));

            // Opening = net of everything before the period start.
            $before = (clone $base)
                ->whereDate('date', '<', $from)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0) as qty,
                    COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity * unit_cost ELSE -quantity * unit_cost END), 0) as val
                ")
                ->first();

            $opening = ['quantity' => (float) $before->qty, 'value' => (float) $before->val];

            $movements = (clone $base)
                ->with('warehouse:id,name', 'creator:id,name')
                ->whereBetween('date', [$from, $to])
                ->orderBy('date')
                ->orderBy('id')
                ->get();
        }

        return view('inventory.stocks.card', [
            'movements' => $movements,
            'opening' => $opening,
            'product' => $productId ? Product::with('uom')->find($productId) : null,
            'products' => Product::stockable()->active()->orderBy('name')->get()
                ->mapWithKeys(fn (Product $p) => [$p->id => $p->label()]),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'filters' => compact('productId', 'warehouseId', 'from', 'to'),
        ]);
    }

    /** All movements across products — the raw inventory ledger. */
    public function movements(Request $request): View
    {
        return view('inventory.stocks.movements', [
            'movements' => StockMovement::query()
                ->with(['product:id,sku,name', 'warehouse:id,name'])
                ->filter($request->query())
                ->latest('date')->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'products' => Product::stockable()->active()->orderBy('name')->get()
                ->mapWithKeys(fn (Product $p) => [$p->id => $p->label()]),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'refTypes' => StockMovement::query()
                ->select('ref_type')->distinct()->orderBy('ref_type')->pluck('ref_type', 'ref_type'),
        ]);
    }

    /** Per-warehouse valuation used on the inventory report page. */
    public function valuation(Request $request): View
    {
        $rows = DB::table('stocks')
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->groupBy('warehouses.id', 'warehouses.name')
            ->selectRaw('warehouses.name as warehouse, COALESCE(SUM(stocks.quantity),0) as qty, COALESCE(SUM(stocks.quantity * stocks.avg_cost),0) as value')
            ->orderBy('warehouses.name')
            ->get();

        return view('inventory.stocks.valuation', ['rows' => $rows]);
    }
}
