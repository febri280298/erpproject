<?php

namespace Database\Seeders;

use App\Models\Master\Partner;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use App\Models\Purchasing\GoodsReceipt;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\LineItemCalculator;
use App\Services\Posting\PurchasingPostingService;
use App\Services\Posting\SalesPostingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Walks the full procure-to-pay and order-to-cash flow so a fresh install has
 * stock on hand, posted journals and a dashboard with real numbers.
 */
class DemoTransactionSeeder extends Seeder
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly LineItemCalculator $calculator,
        private readonly PurchasingPostingService $purchasing,
        private readonly SalesPostingService $sales,
    ) {}

    public function run(): void
    {
        // Posting services stamp created_by from the session; act as the admin.
        Auth::login(User::where('email', 'admin@bonecomtricom.com')->firstOrFail());

        $warehouse = Warehouse::where('is_default', true)->firstOrFail();
        $supplier = Partner::where('code', 'SUP-0001')->firstOrFail();
        $customers = Partner::customers()->orderBy('code')->get();
        $products = Product::stockable()->orderBy('sku')->get();

        $this->stockUp($supplier, $warehouse, $products);
        $this->sell($customers, $warehouse, $products);

        Auth::logout();

        $this->command?->info('Transaksi demo (pembelian, penerimaan, penjualan, pengiriman, faktur) dibuat.');
    }

    /** One purchase order per batch of products, fully received and invoiced. */
    private function stockUp(Partner $supplier, Warehouse $warehouse, $products): void
    {
        $date = CarbonImmutable::now()->subMonths(2)->startOfMonth()->addDays(4);

        foreach ($products->chunk(6) as $index => $chunk) {
            $orderDate = $date->addDays($index * 3);

            $rows = $this->calculator->calculate($chunk->map(fn (Product $p) => [
                'product_id' => $p->id,
                'description' => $p->name,
                'quantity' => 25,
                'unit_price' => (float) $p->purchase_price,
                'discount_percent' => 0,
                'tax_rate' => 11,
            ])->all());

            $totals = $this->calculator->totals($rows);

            $order = PurchaseOrder::create(array_merge($totals, [
                'po_no' => $this->numbers->next('purchase_order', $orderDate->toDateString()),
                'date' => $orderDate->toDateString(),
                'expected_date' => $orderDate->addDays(7)->toDateString(),
                'partner_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => $orderDate,
                'created_by' => Auth::id(),
                'notes' => 'Pengadaan stok awal.',
            ]));

            $order->items()->createMany(array_map(fn ($row) => collect($row)->only([
                'product_id', 'description', 'quantity', 'unit_price',
                'discount_percent', 'tax_rate', 'tax_amount', 'subtotal', 'total',
            ])->all(), $rows));

            $order->load('items');

            $receipt = GoodsReceipt::create([
                'grn_no' => $this->numbers->next('goods_receipt', $orderDate->addDays(5)->toDateString()),
                'date' => $orderDate->addDays(5)->toDateString(),
                'purchase_order_id' => $order->id,
                'partner_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $receipt->items()->createMany($order->items->map(fn ($item) => [
                'purchase_order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ])->all());

            $this->purchasing->postGoodsReceipt($receipt);

            $invoice = PurchaseInvoice::create(array_merge($totals, [
                'invoice_no' => $this->numbers->next('purchase_invoice', $orderDate->addDays(6)->toDateString()),
                'supplier_invoice_no' => 'INV-SUP-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'date' => $orderDate->addDays(6)->toDateString(),
                'due_date' => $orderDate->addDays(36)->toDateString(),
                'partner_id' => $supplier->id,
                'purchase_order_id' => $order->id,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]));

            $invoice->items()->createMany(array_map(fn ($row) => collect($row)->only([
                'product_id', 'description', 'quantity', 'unit_price',
                'discount_percent', 'tax_rate', 'tax_amount', 'subtotal', 'total',
            ])->all(), $rows));

            $this->purchasing->postInvoice($invoice);
        }
    }

    /** A handful of sales orders, delivered and invoiced. */
    private function sell($customers, Warehouse $warehouse, $products): void
    {
        $start = CarbonImmutable::now()->subMonth()->startOfMonth();

        for ($i = 0; $i < 6; $i++) {
            $customer = $customers[$i % $customers->count()];
            $orderDate = $start->addDays($i * 5);
            $picked = $products->random(min(3, $products->count()));

            $rows = $this->calculator->calculate($picked->map(fn (Product $p) => [
                'product_id' => $p->id,
                'description' => $p->name,
                'quantity' => random_int(1, 5),
                'unit_price' => (float) $p->sale_price,
                'discount_percent' => 0,
                'tax_rate' => 11,
            ])->values()->all());

            $totals = $this->calculator->totals($rows);

            $order = SalesOrder::create(array_merge($totals, [
                'so_no' => $this->numbers->next('sales_order', $orderDate->toDateString()),
                'date' => $orderDate->toDateString(),
                'delivery_date' => $orderDate->addDays(3)->toDateString(),
                'partner_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'status' => 'confirmed',
                'approved_by' => Auth::id(),
                'approved_at' => $orderDate,
                'created_by' => Auth::id(),
            ]));

            $order->items()->createMany(array_map(fn ($row) => collect($row)->only([
                'product_id', 'description', 'quantity', 'unit_price',
                'discount_percent', 'tax_rate', 'tax_amount', 'subtotal', 'total',
            ])->all(), $rows));

            $order->load('items');

            $delivery = DeliveryOrder::create([
                'do_no' => $this->numbers->next('delivery_order', $orderDate->addDays(3)->toDateString()),
                'date' => $orderDate->addDays(3)->toDateString(),
                'sales_order_id' => $order->id,
                'partner_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'shipping_address' => $customer->address,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $delivery->items()->createMany($order->items->map(fn ($item) => [
                'sales_order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
            ])->all());

            $this->sales->postDelivery($delivery);

            $invoice = SalesInvoice::create(array_merge($totals, [
                'invoice_no' => $this->numbers->next('sales_invoice', $orderDate->addDays(4)->toDateString()),
                'date' => $orderDate->addDays(4)->toDateString(),
                'due_date' => $orderDate->addDays(34)->toDateString(),
                'partner_id' => $customer->id,
                'sales_order_id' => $order->id,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]));

            $invoice->items()->createMany(array_map(fn ($row) => collect($row)->only([
                'product_id', 'description', 'quantity', 'unit_price',
                'discount_percent', 'tax_rate', 'tax_amount', 'subtotal', 'total',
            ])->all(), $rows));

            $this->sales->postInvoice($invoice);
        }
    }
}
