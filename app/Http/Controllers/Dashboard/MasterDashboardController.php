<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Master\Partner;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard modul Data Master.
 *
 * Berbeda dari dashboard modul lain yang menampilkan uang dan dokumen, yang
 * berguna di sini adalah KELENGKAPAN data. Produk tanpa satuan atau tanpa
 * pajak baru ketahuan saat dipakai di dokumen — biasanya ketika orang sedang
 * buru-buru membuat faktur. Daftar di bawah memunculkannya lebih awal.
 */
class MasterDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboards.master', [
            'stats' => [
                'produk' => Product::where('is_active', true)->count(),
                'customer' => Partner::whereIn('type', [Partner::TYPE_CUSTOMER, Partner::TYPE_BOTH])->count(),
                'supplier' => Partner::whereIn('type', [Partner::TYPE_SUPPLIER, Partner::TYPE_BOTH])->count(),
                'gudang' => Warehouse::where('is_active', true)->count(),
            ],
            'kelengkapan' => $this->kelengkapan(),
            'perKategori' => $this->perKategori(),
            'produkTerbaru' => Product::with('category:id,name', 'uom:id,code')
                ->latest('id')->limit(8)->get(),
            'mitraTerbaru' => Partner::latest('id')->limit(8)->get(),
        ]);
    }

    /**
     * Data yang belum lengkap, masing-masing dengan tautan ke daftar yang perlu
     * diperbaiki. Nol berarti beres — dan itu memang yang ingin dilihat.
     *
     * @return array<int,array<string,mixed>>
     */
    private function kelengkapan(): array
    {
        $produkAktif = Product::where('is_active', true);

        // Tautan perbaikan hanya diberikan bila penggunanya berhak membuka
        // daftarnya; angkanya tetap ditampilkan sebagai informasi.
        $keMitra = auth()->user()->can('partner.view') ? route('partners.index') : null;

        return [
            [
                'label' => 'Produk tanpa kategori',
                'count' => (clone $produkAktif)->whereNull('product_category_id')->count(),
                'href' => route('products.index'),
                'catatan' => 'Menyulitkan pengelompokan di laporan.',
            ],
            [
                'label' => 'Produk tanpa satuan',
                'count' => (clone $produkAktif)->whereNull('uom_id')->count(),
                'href' => route('products.index'),
                'catatan' => 'Kolom satuan pada dokumen akan kosong.',
            ],
            [
                'label' => 'Produk tanpa pajak',
                'count' => (clone $produkAktif)->whereNull('tax_id')->count(),
                'href' => route('products.index'),
                'catatan' => 'PPN tidak terhitung otomatis saat dipakai.',
            ],
            [
                'label' => 'Produk tanpa harga jual',
                'count' => (clone $produkAktif)->where('type', Product::TYPE_STOCK)
                    ->where('sale_price', '<=', 0)->count(),
                'href' => route('products.index'),
                'catatan' => 'Baris dokumen terisi nol dan mudah terlewat.',
            ],
            [
                'label' => 'Mitra tanpa inisial',
                'count' => Partner::whereNull('initial')->count(),
                'href' => $keMitra,
                'catatan' => 'Inisial dipakai sebagai penanda singkat sehari-hari.',
            ],
            [
                'label' => 'Mitra tanpa termin',
                'count' => Partner::whereNull('payment_term_id')->count(),
                'href' => $keMitra,
                'catatan' => 'Tanggal jatuh tempo faktur harus diisi manual.',
            ],
        ];
    }

    private function perKategori()
    {
        return DB::table('products')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->groupBy('product_categories.id', 'product_categories.name')
            ->selectRaw('COALESCE(product_categories.name, ?) as nama, COUNT(*) as jumlah', ['Tanpa kategori'])
            ->orderByDesc('jumlah')
            ->get();
    }
}
