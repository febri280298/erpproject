<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockMovement;
use App\Models\Master\Product;
use App\Services\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard modul Stok & Gudang.
 *
 * Dua hal yang paling berguna bagi orang gudang: barang apa yang hampir habis,
 * dan apakah angka di sistem masih masuk akal. Stok negatif diangkat sebagai
 * indikator tersendiri karena nilainya seharusnya selalu nol — kalau tidak,
 * ada penerimaan yang belum diposting atau pengeluaran yang dobel.
 */
class InventoryDashboardController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function __invoke(): View
    {
        return view('dashboards.inventory', [
            'stats' => [
                'nilai_persediaan' => $this->inventory->totalValue(),
                'produk_aktif' => Product::where('is_active', true)->where('type', Product::TYPE_STOCK)->count(),
                'menipis' => $this->jumlahMenipis(),
                'negatif' => Stock::where('quantity', '<', 0)->count(),
            ],
            'alur' => $this->alur(),
            'stokMenipis' => $this->stokMenipis(),
            'perGudang' => $this->perGudang(),
            'pergerakanTerakhir' => StockMovement::with('product:id,sku,name', 'warehouse:id,name')
                ->latest('date')->latest('id')->limit(10)->get(),
            'tanpaPergerakan' => $this->tanpaPergerakan(),
        ]);
    }

    private function jumlahMenipis(): int
    {
        return Stock::query()
            ->whereHas('product', fn ($q) => $q->where('min_stock', '>', 0)->where('is_active', true))
            ->whereRaw('stocks.quantity < (SELECT min_stock FROM products WHERE products.id = stocks.product_id)')
            ->count();
    }

    /**
     * Tahap disembunyikan bila penggunanya tidak berhak membuka halaman
     * tujuannya, agar dashboard tidak memuat tautan yang berujung 403.
     *
     * @return array<int,array<string,mixed>>
     */
    private function alur(): array
    {
        $pengguna = auth()->user();

        return [
            [
                'label' => 'Penerimaan',
                'hint' => 'GRN masih draft',
                'count' => DB::table('goods_receipts')->where('status', 'draft')->count(),
                'href' => route('goods-receipts.index'),
                'tampil' => $pengguna->can('goods-receipt.view'),
            ],
            [
                'label' => 'Pengiriman',
                'hint' => 'surat jalan draft',
                'count' => DB::table('delivery_orders')->where('status', 'draft')->count(),
                'href' => route('delivery-orders.index'),
                'tampil' => $pengguna->can('delivery-order.view'),
            ],
            [
                'label' => 'Transfer',
                'hint' => 'belum diposting',
                'count' => DB::table('stock_transfers')->where('status', 'draft')->count(),
                'href' => route('stock-transfers.index'),
                'tampil' => $pengguna->can('stock-transfer.view'),
            ],
            [
                'label' => 'Penyesuaian',
                'hint' => 'opname belum selesai',
                'count' => DB::table('stock_adjustments')->where('status', 'draft')->count(),
                'href' => route('stock-adjustments.index'),
                'tampil' => $pengguna->can('stock-adjustment.view'),
            ],
        ];
    }

    private function stokMenipis()
    {
        return Stock::query()
            ->with('product:id,sku,name,min_stock,uom_id', 'product.uom:id,code', 'warehouse:id,name')
            ->whereHas('product', fn ($q) => $q->where('min_stock', '>', 0)->where('is_active', true))
            ->whereRaw('stocks.quantity < (SELECT min_stock FROM products WHERE products.id = stocks.product_id)')
            ->orderBy('quantity')
            ->limit(10)
            ->get();
    }

    private function perGudang()
    {
        return DB::table('stocks')
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->groupBy('warehouses.id', 'warehouses.name')
            ->selectRaw('warehouses.name, COUNT(DISTINCT stocks.product_id) as produk, '
                .'SUM(stocks.quantity) as qty, SUM(stocks.quantity * stocks.avg_cost) as nilai')
            ->orderByDesc('nilai')
            ->get();
    }

    /**
     * Barang bersisa stok yang tidak bergerak 90 hari terakhir — modal yang
     * mengendap, dan calon barang kedaluwarsa atau rusak.
     */
    private function tanpaPergerakan()
    {
        $batas = CarbonImmutable::now()->subDays(90)->toDateString();

        return DB::table('stocks')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->where('stocks.quantity', '>', 0)
            ->whereNotExists(function ($q) use ($batas) {
                $q->select(DB::raw(1))
                    ->from('stock_movements')
                    ->whereColumn('stock_movements.product_id', 'stocks.product_id')
                    ->whereDate('stock_movements.date', '>=', $batas);
            })
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->selectRaw('products.sku, products.name, SUM(stocks.quantity) as qty, '
                .'SUM(stocks.quantity * stocks.avg_cost) as nilai')
            ->orderByDesc('nilai')
            ->limit(8)
            ->get();
    }
}
