<?php

namespace App\Http\Controllers;

use App\Models\Hr\Employee;
use App\Models\Inventory\Stock;
use App\Models\Master\Product;
use App\Models\Master\Tax;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use App\Services\AccountMap;
use App\Services\InventoryService;
use App\Services\LineItemCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly AccountMap $accounts,
    ) {}

    public function index(): View
    {
        $monthStart = CarbonImmutable::now()->startOfMonth();
        $monthEnd = CarbonImmutable::now()->endOfMonth();

        return view('dashboard', [
            'stats' => $this->stats($monthStart, $monthEnd),
            'salesTrend' => $this->salesTrend(),
            'topProducts' => $this->topProducts($monthStart, $monthEnd),
            'lowStock' => $this->lowStock(),
            'recentSales' => SalesOrder::with('customer:id,name')->latest('date')->latest('id')->limit(6)->get(),
            'recentPurchases' => PurchaseOrder::with('supplier:id,name')->latest('date')->latest('id')->limit(6)->get(),
            'overdueReceivables' => SalesInvoice::with('customer:id,name')->unpaid()
                ->whereDate('due_date', '<', now())->orderBy('due_date')->limit(6)->get(),
            'pendingApprovals' => $this->pendingApprovals(),
            'missingAccounts' => $this->accounts->missing(),
            'taxMisconfigured' => $this->taxMisconfiguration(),
        ]);
    }

    private function stats(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $salesThisMonth = (float) SalesInvoice::whereBetween('date', [$from, $to])
            ->whereNotIn('status', ['draft', 'cancelled'])->sum('total');

        $purchaseThisMonth = (float) PurchaseInvoice::whereBetween('date', [$from, $to])
            ->whereNotIn('status', ['draft', 'cancelled'])->sum('total');

        return [
            'sales_month' => $salesThisMonth,
            'purchase_month' => $purchaseThisMonth,
            'receivable' => (float) SalesInvoice::unpaid()->sum(DB::raw('total - paid_amount')),
            'payable' => (float) PurchaseInvoice::unpaid()->sum(DB::raw('total - paid_amount')),
            'inventory_value' => $this->inventory->totalValue(),
            'open_sales_orders' => SalesOrder::whereIn('status', ['confirmed', 'partial'])->count(),
            'open_purchase_orders' => PurchaseOrder::whereIn('status', ['approved', 'partial'])->count(),
            'products' => Product::where('is_active', true)->count(),
            'employees' => Employee::active()->count(),
        ];
    }

    /** Last 12 months of sales vs purchases, for the ApexCharts area chart. */
    private function salesTrend(): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(11);

        $pull = function (string $table) use ($start) {
            return DB::table($table)
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->whereDate('date', '>=', $start->toDateString())
                ->groupByRaw('YEAR(date), MONTH(date)')
                ->selectRaw('YEAR(date) as y, MONTH(date) as m, COALESCE(SUM(total),0) as amount')
                ->get()
                ->keyBy(fn ($row) => sprintf('%04d-%02d', $row->y, $row->m));
        };

        $sales = $pull('sales_invoices');
        $purchases = $pull('purchase_invoices');

        $labels = [];
        $salesSeries = [];
        $purchaseSeries = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $start->addMonths($i);
            $key = $month->format('Y-m');

            $labels[] = $month->translatedFormat('M y');
            $salesSeries[] = round((float) ($sales->get($key)->amount ?? 0), 2);
            $purchaseSeries[] = round((float) ($purchases->get($key)->amount ?? 0), 2);
        }

        return ['labels' => $labels, 'sales' => $salesSeries, 'purchases' => $purchaseSeries];
    }

    private function topProducts(CarbonImmutable $from, CarbonImmutable $to)
    {
        return DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('sales_invoices.date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->selectRaw('products.sku, products.name, SUM(sales_invoice_items.quantity) as qty, SUM(sales_invoice_items.total) as revenue')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();
    }

    private function lowStock()
    {
        return Stock::query()
            ->with('product:id,sku,name,min_stock,uom_id', 'product.uom:id,code', 'warehouse:id,name')
            ->whereHas('product', fn ($q) => $q->where('min_stock', '>', 0)->where('is_active', true))
            ->whereRaw('stocks.quantity < (SELECT min_stock FROM products WHERE products.id = stocks.product_id)')
            ->orderBy('quantity')
            ->limit(8)
            ->get();
    }

    /**
     * DPP Nilai Lain yang aktif berpasangan dengan tarif selain 12% menghasilkan
     * pajak kurang bayar tanpa gejala di layar, jadi kondisinya diangkat ke
     * dashboard agar tidak berjalan diam-diam berbulan-bulan.
     */
    private function taxMisconfiguration(): ?string
    {
        $calculator = app(LineItemCalculator::class);

        if (! $calculator->isDppOtherEnabled()) {
            return null;
        }

        $tarif = (float) Tax::where('is_default', true)->value('rate');

        if (abs($tarif - 12) < 0.01) {
            return null;
        }

        return sprintf(
            'DPP Nilai Lain (%s) aktif, tetapi pajak default masih %s%%. '
            .'Pajak yang dihitung menjadi %s%% — kurang dari seharusnya.',
            $calculator->ratioLabel(),
            fnum($tarif),
            fnum($tarif * $calculator->ratio()),
        );
    }

    private function pendingApprovals(): array
    {
        return [
            'requisitions' => DB::table('purchase_requisitions')->where('status', 'submitted')->count(),
            'purchase_orders' => DB::table('purchase_orders')->where('status', 'draft')->count(),
            'sales_orders' => DB::table('sales_orders')->where('status', 'draft')->count(),
            'leaves' => DB::table('leaves')->where('status', 'pending')->count(),
            'draft_receipts' => DB::table('goods_receipts')->where('status', 'draft')->count(),
            'draft_deliveries' => DB::table('delivery_orders')->where('status', 'draft')->count(),
        ];
    }
}
