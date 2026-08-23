<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\BuildsMonthlySeries;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use App\Services\ModuleRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard modul Penjualan.
 *
 * Menjawab tiga pertanyaan yang selalu ditanyakan orang penjualan: berapa omzet
 * bulan ini, siapa yang belum bayar, dan pesanan mana yang belum dikirim.
 */
class SalesDashboardController extends Controller
{
    use BuildsMonthlySeries;

    public function __invoke(ModuleRegistry $modules): View
    {
        $awal = CarbonImmutable::now()->startOfMonth();
        $akhir = CarbonImmutable::now()->endOfMonth();

        // Sejalan dengan dashboard Pembelian: mengikuti report.view, karena
        // omzet dan umur piutang sudah tersedia lewat modul Laporan bagi
        // pemegang izin yang sama.
        $bolehUang = auth()->user()->can('report.view');

        return view('dashboards.sales', [
            'bolehUang' => $bolehUang,
            'stats' => $bolehUang ? $this->stats($awal, $akhir) : null,
            'alur' => $this->alur($modules),
            'grafik' => $this->monthlySeries(['Penjualan' => ['tabel' => 'sales_invoices']]),
            'soTerbaru' => SalesOrder::with('customer:id,name')
                ->latest('date')->latest('id')->limit(8)->get(),
            'jatuhTempo' => $bolehUang
                ? SalesInvoice::with('customer:id,name')
                    ->unpaid()->whereDate('due_date', '<=', now()->addDays(7))
                    ->orderBy('due_date')->limit(8)->get()
                : collect(),
            'customerTeratas' => $bolehUang ? $this->customerTeratas($awal, $akhir) : collect(),
            'produkTeratas' => $bolehUang ? $this->produkTeratas($awal, $akhir) : collect(),
            'grafikTampil' => $bolehUang,
        ]);
    }

    /** @return array<string,float|int> */
    private function stats(CarbonImmutable $awal, CarbonImmutable $akhir): array
    {
        return [
            'omzet_bulan_ini' => (float) SalesInvoice::whereBetween('date', [$awal, $akhir])
                ->whereNotIn('status', ['draft', 'cancelled'])->sum('total'),

            'piutang' => (float) SalesInvoice::unpaid()->sum(DB::raw('total - paid_amount')),

            'piutang_jatuh_tempo' => (float) SalesInvoice::unpaid()
                ->whereDate('due_date', '<', now())->sum(DB::raw('total - paid_amount')),

            'so_terbuka' => SalesOrder::whereIn('status', ['confirmed', 'approved', 'partial'])->count(),
        ];
    }

    /**
     * Tahap disembunyikan bila modulnya mati atau penggunanya tidak berhak
     * membuka halaman tujuannya — tanpa itu alurnya memuat tautan 403.
     *
     * @return array<int,array<string,mixed>>
     */
    private function alur(ModuleRegistry $modules): array
    {
        $pengguna = auth()->user();

        return [
            [
                'label' => 'Penawaran',
                'hint' => 'belum jadi pesanan',
                'count' => Quotation::whereNotIn('status', ['cancelled', 'closed', 'converted'])->count(),
                'href' => route('quotations.index'),
                'tampil' => $modules->enabled('quotation') && $pengguna->can('quotation.view'),
            ],
            [
                'label' => 'Pesanan (SO)',
                'hint' => 'masih draft',
                'count' => SalesOrder::where('status', 'draft')->count(),
                'href' => route('sales-orders.index'),
                'tampil' => $pengguna->can('sales-order.view'),
            ],
            [
                'label' => 'Surat Jalan',
                'hint' => 'belum diposting',
                'count' => DeliveryOrder::where('status', 'draft')->count(),
                'href' => route('delivery-orders.index'),
                'tampil' => $pengguna->can('delivery-order.view'),
            ],
            [
                'label' => 'Faktur',
                'hint' => 'masih draft',
                'count' => SalesInvoice::where('status', 'draft')->count(),
                'href' => route('sales-invoices.index'),
                'tampil' => $pengguna->can('sales-invoice.view'),
            ],
            [
                'label' => 'Penagihan',
                'hint' => 'faktur belum lunas',
                'count' => SalesInvoice::unpaid()->count(),
                'href' => route('customer-payments.index'),
                'tampil' => $pengguna->can('customer-payment.view'),
                'color' => 'red',
            ],
        ];
    }

    private function customerTeratas(CarbonImmutable $awal, CarbonImmutable $akhir)
    {
        return DB::table('sales_invoices')
            ->join('partners', 'partners.id', '=', 'sales_invoices.partner_id')
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('sales_invoices.date', [$awal->toDateString(), $akhir->toDateString()])
            ->groupBy('partners.id', 'partners.name', 'partners.initial')
            ->selectRaw('partners.name, partners.initial, COUNT(*) as dokumen, SUM(sales_invoices.total) as nilai')
            ->orderByDesc('nilai')
            ->limit(5)
            ->get();
    }

    private function produkTeratas(CarbonImmutable $awal, CarbonImmutable $akhir)
    {
        return DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('sales_invoices.date', [$awal->toDateString(), $akhir->toDateString()])
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->selectRaw('products.sku, products.name, SUM(sales_invoice_items.quantity) as qty, SUM(sales_invoice_items.total) as nilai')
            ->orderByDesc('nilai')
            ->limit(5)
            ->get();
    }
}
