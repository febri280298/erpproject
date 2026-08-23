<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\BuildsMonthlySeries;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\PurchaseOrder;
use App\Services\ModuleRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard modul Pembelian.
 *
 * Isinya dibatasi pada apa yang dikerjakan orang pembelian: berapa yang sudah
 * dibelanjakan, berapa yang masih harus dibayar, dan dokumen mana yang tertahan.
 * Angka penjualan sengaja tidak ikut — itu urusan dashboard lain, dan harga
 * beli tidak perlu bercampur dengan omzet di layar yang sama.
 */
class PurchasingDashboardController extends Controller
{
    use BuildsMonthlySeries;

    public function __invoke(ModuleRegistry $modules): View
    {
        $awal = CarbonImmutable::now()->startOfMonth();
        $akhir = CarbonImmutable::now()->endOfMonth();

        /*
         * Nilai ringkasan keuangan mengikuti izin `report.view`, bukan izin
         * faktur.
         *
         * Alasannya konsistensi: angka yang sama — belanja per bulan dan umur
         * utang — sudah dapat dibuka lewat modul Laporan oleh siapa pun yang
         * memegang report.view. Menyembunyikannya di sini tidak menutup apa pun,
         * hanya membuat dashboard berbeda dari Laporan, dan justru menghilangkan
         * angka itu dari Manajer yang memang tidak memegang izin faktur.
         *
         * Bila suatu peran memang tidak boleh melihat angka ini, yang dicabut
         * adalah report.view pada perannya, bukan ditambal per layar.
         */
        $bolehUang = auth()->user()->can('report.view');

        return view('dashboards.purchasing', [
            'bolehUang' => $bolehUang,
            'stats' => $bolehUang ? $this->stats($awal, $akhir) : null,
            'alur' => $this->alur($modules),
            'grafik' => $this->monthlySeries(['Pembelian' => ['tabel' => 'purchase_invoices']]),
            'poTerbaru' => PurchaseOrder::with('supplier:id,name')
                ->latest('date')->latest('id')->limit(8)->get(),
            'jatuhTempo' => $bolehUang
                ? PurchaseInvoice::with('supplier:id,name')
                    ->unpaid()->whereDate('due_date', '<=', now()->addDays(7))
                    ->orderBy('due_date')->limit(8)->get()
                : collect(),
            'supplierTeratas' => $bolehUang ? $this->supplierTeratas($awal, $akhir) : collect(),
            'grafikTampil' => $bolehUang,
        ]);
    }

    /** @return array<string,float|int> */
    private function stats(CarbonImmutable $awal, CarbonImmutable $akhir): array
    {
        return [
            'belanja_bulan_ini' => (float) PurchaseInvoice::whereBetween('date', [$awal, $akhir])
                ->whereNotIn('status', ['draft', 'cancelled'])->sum('total'),

            'utang' => (float) PurchaseInvoice::unpaid()->sum(DB::raw('total - paid_amount')),

            // Yang sudah lewat jatuh tempo dipisah dari total utang: keduanya
            // menuntut tindakan yang berbeda.
            'utang_jatuh_tempo' => (float) PurchaseInvoice::unpaid()
                ->whereDate('due_date', '<', now())->sum(DB::raw('total - paid_amount')),

            'po_terbuka' => PurchaseOrder::whereIn('status', ['approved', 'partial'])->count(),
        ];
    }

    /**
     * Jumlah dokumen yang tertahan di tiap tahap alur pembelian.
     *
     * Tahap Permintaan Pembelian hanya muncul bila modulnya menyala — bila
     * dimatikan, alurnya memang langsung ke PO dan menampilkannya justru
     * menyesatkan.
     *
     * Tiap tahap juga disembunyikan bila penggunanya tidak berhak membuka
     * halaman tujuannya. Peran Gudang, misalnya, boleh melihat PO tetapi tidak
     * faktur — menampilkan tahapnya hanya menghasilkan tautan yang berujung 403.
     *
     * @return array<int,array<string,mixed>>
     */
    private function alur(ModuleRegistry $modules): array
    {
        $pengguna = auth()->user();

        return [
            [
                'label' => 'Permintaan',
                'hint' => 'menunggu disetujui',
                'count' => DB::table('purchase_requisitions')->where('status', 'submitted')->count(),
                'href' => route('purchase-requisitions.index'),
                'tampil' => $modules->enabled('purchase_requisition')
                    && $pengguna->can('purchase-requisition.view'),
            ],
            [
                'label' => 'Pesanan (PO)',
                'hint' => 'masih draft',
                'count' => PurchaseOrder::where('status', 'draft')->count(),
                'href' => route('purchase-orders.index'),
                'tampil' => $pengguna->can('purchase-order.view'),
            ],
            [
                'label' => 'Penerimaan',
                'hint' => 'PO belum lengkap diterima',
                'count' => PurchaseOrder::whereIn('status', ['approved', 'partial'])->count(),
                'href' => route('goods-receipts.index'),
                'tampil' => $pengguna->can('goods-receipt.view'),
            ],
            [
                'label' => 'Faktur',
                'hint' => 'masih draft',
                'count' => PurchaseInvoice::where('status', 'draft')->count(),
                'href' => route('purchase-invoices.index'),
                'tampil' => $pengguna->can('purchase-invoice.view'),
            ],
            [
                'label' => 'Pembayaran',
                'hint' => 'faktur belum lunas',
                'count' => PurchaseInvoice::unpaid()->count(),
                'href' => route('supplier-payments.index'),
                'tampil' => $pengguna->can('supplier-payment.view'),
                'color' => 'red',
            ],
        ];
    }

    private function supplierTeratas(CarbonImmutable $awal, CarbonImmutable $akhir)
    {
        return DB::table('purchase_invoices')
            ->join('partners', 'partners.id', '=', 'purchase_invoices.partner_id')
            ->whereNotIn('purchase_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('purchase_invoices.date', [$awal->toDateString(), $akhir->toDateString()])
            ->groupBy('partners.id', 'partners.name', 'partners.initial')
            ->selectRaw('partners.name, partners.initial, COUNT(*) as dokumen, SUM(purchase_invoices.total) as nilai')
            ->orderByDesc('nilai')
            ->limit(5)
            ->get();
    }
}
