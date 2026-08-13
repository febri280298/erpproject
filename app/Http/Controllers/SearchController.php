<?php

namespace App\Http\Controllers;

use App\Models\Master\Partner;
use App\Models\Master\Product;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use App\Services\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global search from the top bar — covers master data and document numbers,
 * skipping anything the current user has no permission to see.
 */
class SearchController extends Controller
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    public function __invoke(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $user = $request->user();
        $results = [];

        // A hit is only useful if its module is switched on and the user may see it.
        $allowed = fn (string $permission, string $module) => $user->can($permission)
            && $this->modules->enabled($module);

        if ($term !== '') {
            if ($user->can('product.view')) {
                $results['Produk'] = Product::search($term)->limit(8)->get()
                    ->map(fn (Product $p) => ['label' => $p->label(), 'meta' => $p->category?->name, 'url' => route('products.show', $p)]);
            }

            if ($user->can('partner.view')) {
                $results['Mitra Bisnis'] = Partner::search($term)->limit(8)->get()
                    ->map(fn (Partner $p) => ['label' => $p->code.' — '.$p->name, 'meta' => $p->typeLabel(), 'url' => route('partners.show', $p)]);
            }

            if ($allowed('sales-order.view', 'sales')) {
                $results['Pesanan Penjualan'] = SalesOrder::where('so_no', 'like', "%{$term}%")->limit(8)->get()
                    ->map(fn (SalesOrder $o) => ['label' => $o->so_no, 'meta' => fdate($o->date).' · '.rupiah($o->total), 'url' => route('sales-orders.show', $o)]);
            }

            if ($allowed('purchase-order.view', 'purchasing')) {
                $results['Pesanan Pembelian'] = PurchaseOrder::where('po_no', 'like', "%{$term}%")->limit(8)->get()
                    ->map(fn (PurchaseOrder $o) => ['label' => $o->po_no, 'meta' => fdate($o->date).' · '.rupiah($o->total), 'url' => route('purchase-orders.show', $o)]);
            }

            if ($allowed('sales-invoice.view', 'sales')) {
                $results['Faktur Penjualan'] = SalesInvoice::where('invoice_no', 'like', "%{$term}%")->limit(8)->get()
                    ->map(fn (SalesInvoice $i) => ['label' => $i->invoice_no, 'meta' => fdate($i->date).' · '.rupiah($i->total), 'url' => route('sales-invoices.show', $i)]);
            }

            $results = array_filter($results, fn ($group) => $group->isNotEmpty());
        }

        return view('search', ['term' => $term, 'results' => $results]);
    }
}
