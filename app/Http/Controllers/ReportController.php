<?php

namespace App\Http\Controllers;

use App\Models\Master\Partner;
use App\Models\Master\Warehouse;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Sales\SalesInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function sales(Request $request): View
    {
        [$from, $to] = $this->period($request);

        $invoices = SalesInvoice::query()
            ->with('customer:id,name')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('date', [$from, $to])
            ->when($request->filled('partner_id'), fn ($q) => $q->where('partner_id', $request->query('partner_id')))
            ->orderBy('date')
            ->get();

        $byCustomer = $invoices->groupBy('partner_id')->map(fn ($group) => [
            'name' => $group->first()->customer?->name ?? '—',
            'count' => $group->count(),
            'total' => $group->sum('total'),
        ])->sortByDesc('total')->values();

        $byMonth = $invoices->groupBy(fn ($i) => $i->date->format('Y-m'))
            ->map(fn ($group, $key) => ['month' => $key, 'total' => $group->sum('total')])
            ->values();

        return view('reports.sales', [
            'invoices' => $invoices,
            'byCustomer' => $byCustomer,
            'byMonth' => $byMonth,
            'summary' => [
                'count' => $invoices->count(),
                'subtotal' => $invoices->sum('subtotal'),
                'tax' => $invoices->sum('tax_amount'),
                'total' => $invoices->sum('total'),
                'paid' => $invoices->sum('paid_amount'),
            ],
            'customers' => Partner::customers()->orderBy('name')->pluck('name', 'id'),
            'filters' => compact('from', 'to'),
        ]);
    }

    public function purchasing(Request $request): View
    {
        [$from, $to] = $this->period($request);

        $invoices = PurchaseInvoice::query()
            ->with('supplier:id,name')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('date', [$from, $to])
            ->when($request->filled('partner_id'), fn ($q) => $q->where('partner_id', $request->query('partner_id')))
            ->orderBy('date')
            ->get();

        $bySupplier = $invoices->groupBy('partner_id')->map(fn ($group) => [
            'name' => $group->first()->supplier?->name ?? '—',
            'count' => $group->count(),
            'total' => $group->sum('total'),
        ])->sortByDesc('total')->values();

        return view('reports.purchasing', [
            'invoices' => $invoices,
            'bySupplier' => $bySupplier,
            'summary' => [
                'count' => $invoices->count(),
                'subtotal' => $invoices->sum('subtotal'),
                'tax' => $invoices->sum('tax_amount'),
                'total' => $invoices->sum('total'),
                'paid' => $invoices->sum('paid_amount'),
            ],
            'suppliers' => Partner::suppliers()->orderBy('name')->pluck('name', 'id'),
            'filters' => compact('from', 'to'),
        ]);
    }

    public function inventory(Request $request): View
    {
        $warehouseId = $request->query('warehouse_id');

        $rows = DB::table('stocks')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->leftJoin('uoms', 'uoms.id', '=', 'products.uom_id')
            ->whereNull('products.deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('stocks.warehouse_id', $warehouseId))
            ->where('stocks.quantity', '!=', 0)
            ->orderBy('products.name')
            ->select(
                'products.sku', 'products.name', 'products.min_stock', 'uoms.code as uom',
                'warehouses.name as warehouse', 'stocks.quantity', 'stocks.avg_cost',
                DB::raw('stocks.quantity * stocks.avg_cost as value')
            )
            ->get();

        return view('reports.inventory', [
            'rows' => $rows,
            'totalValue' => $rows->sum('value'),
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
            'warehouseId' => $warehouseId,
        ]);
    }

    /** Receivable aging, bucketed by how long each invoice is overdue. */
    public function receivableAging(Request $request): View
    {
        return view('reports.aging', $this->aging(
            SalesInvoice::query()->with('customer:id,name')->unpaid()->orderBy('due_date')->get(),
            'customer',
            'Umur Piutang',
            'Pelanggan',
        ));
    }

    public function payableAging(Request $request): View
    {
        return view('reports.aging', $this->aging(
            PurchaseInvoice::query()->with('supplier:id,name')->unpaid()->orderBy('due_date')->get(),
            'supplier',
            'Umur Utang',
            'Pemasok',
        ));
    }

    public function topProducts(Request $request): View
    {
        [$from, $to] = $this->period($request);

        $rows = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('sales_invoices.date', [$from, $to])
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->selectRaw('products.sku, products.name,
                SUM(sales_invoice_items.quantity) as qty,
                SUM(sales_invoice_items.subtotal) as revenue,
                COUNT(DISTINCT sales_invoices.id) as invoices')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get();

        return view('reports.top-products', [
            'rows' => $rows,
            'filters' => compact('from', 'to'),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function aging($invoices, string $partnerRelation, string $title, string $partnerLabel): array
    {
        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];

        $rows = $invoices->map(function ($invoice) use ($partnerRelation, &$buckets) {
            $days = $invoice->due_date && $invoice->due_date->isPast()
                ? (int) $invoice->due_date->diffInDays(now())
                : 0;

            $bucket = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => '1_30',
                $days <= 60 => '31_60',
                $days <= 90 => '61_90',
                default => 'over_90',
            };

            $outstanding = $invoice->outstandingAmount();
            $buckets[$bucket] += $outstanding;

            return [
                'invoice_no' => $invoice->invoice_no,
                'partner' => $invoice->{$partnerRelation}?->name ?? '—',
                'date' => $invoice->date,
                'due_date' => $invoice->due_date,
                'days' => $days,
                'total' => (float) $invoice->total,
                'paid' => (float) $invoice->paid_amount,
                'outstanding' => $outstanding,
                'bucket' => $bucket,
            ];
        });

        return [
            'rows' => $rows,
            'buckets' => $buckets,
            'total' => array_sum($buckets),
            'title' => $title,
            'partnerLabel' => $partnerLabel,
        ];
    }

    /** @return array{0:string,1:string} */
    private function period(Request $request): array
    {
        return [
            $request->query('from', now()->startOfMonth()->toDateString()),
            $request->query('to', now()->toDateString()),
        ];
    }
}
