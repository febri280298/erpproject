<?php

namespace App\Services\Posting;

use App\Models\Inventory\StockMovement;
use App\Models\Sales\CustomerPayment;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Services\AccountMap;
use App\Services\InventoryService;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Posts and un-posts the order-to-cash documents.
 *
 * Accounting flow:
 *   Delivery  Dr HPP                Cr Persediaan          (at moving-average cost)
 *   Invoice   Dr Piutang Usaha      Cr Pendapatan Penjualan
 *             Dr Potongan Penjualan Cr PPN Keluaran
 *                                   Cr Beban/Pendapatan Angkut
 *   Receipt   Dr Kas/Bank           Cr Piutang Usaha
 *
 * Cost of goods sold follows the physical movement (delivery), not the invoice,
 * so the ledger and the stock card always agree.
 */
class SalesPostingService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly JournalService $journals,
        private readonly AccountMap $accounts,
    ) {}

    public function postDelivery(DeliveryOrder $delivery): void
    {
        if ($delivery->status !== 'draft') {
            throw new RuntimeException("Surat jalan {$delivery->do_no} sudah diposting.");
        }

        DB::transaction(function () use ($delivery) {
            $delivery->load('items.product', 'salesOrder.items');
            $date = $delivery->date->toDateString();
            $cogs = 0.0;

            foreach ($delivery->items as $item) {
                $movement = $this->inventory->issue(
                    $item->product_id,
                    $delivery->warehouse_id,
                    (float) $item->quantity,
                    'delivery_order',
                    $delivery->id,
                    $delivery->do_no,
                    $date,
                    'Pengiriman ke '.$delivery->customer->name,
                );

                if ($movement) {
                    $cogs += $movement->value();
                }

                if ($item->sales_order_item_id && $item->orderItem) {
                    $item->orderItem->increment('delivered_qty', (float) $item->quantity);
                }
            }

            $cogs = round($cogs, 2);

            if ($cogs > 0) {
                $this->journals->post(
                    [
                        ['account_id' => $this->accounts->id('acc_cogs'), 'debit' => $cogs],
                        ['account_id' => $this->accounts->id('acc_inventory'), 'credit' => $cogs],
                    ],
                    $date,
                    'inventory',
                    'HPP pengiriman '.$delivery->do_no,
                    $delivery,
                    $delivery->do_no,
                );
            }

            $delivery->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
            $delivery->recordActivity('posted', "Surat jalan {$delivery->do_no} diposting");

            $delivery->salesOrder?->fresh()->load('items')->syncDeliveryStatus();
        });
    }

    public function cancelDelivery(DeliveryOrder $delivery): void
    {
        if ($delivery->status !== 'posted') {
            throw new RuntimeException('Hanya surat jalan posted yang bisa dibatalkan.');
        }

        // Membatalkan pengiriman yang sudah punya retur akan menghitung stok dua kali.
        if ($delivery->returns()->where('status', 'posted')->exists()) {
            throw new RuntimeException(
                'Surat jalan ini sudah memiliki retur yang diposting. Batalkan returnya terlebih dahulu.'
            );
        }

        DB::transaction(function () use ($delivery) {
            $delivery->load('items');

            $this->inventory->reverseDocument('delivery_order', $delivery->id, now()->toDateString());
            $this->journals->reverseForSource($delivery);

            foreach ($delivery->items as $item) {
                if ($item->sales_order_item_id && $item->orderItem) {
                    $item->orderItem->decrement('delivered_qty', (float) $item->quantity);
                }
            }

            $delivery->forceFill(['status' => 'cancelled'])->save();
            $delivery->recordActivity('cancelled', "Surat jalan {$delivery->do_no} dibatalkan");

            $delivery->salesOrder?->fresh()->load('items')->syncDeliveryStatus();
        });
    }

    /**
     * Memposting retur penjualan.
     *
     *   Barang baik    Dr Persediaan            Cr HPP     (harga pokok)
     *   Barang rusak   Dr Kerugian Barang Rusak Cr HPP     (tidak masuk stok)
     *   Nota kredit    Dr Retur Penjualan       Cr Piutang (harga jual)
     *                  Dr PPN Keluaran
     *
     * Harga pokok diambil dari pergerakan stok surat jalan asalnya, sehingga
     * barang kembali dengan biaya yang sama seperti saat keluar — bukan dengan
     * rata-rata terbaru yang mungkin sudah bergeser.
     */
    public function postReturn(SalesReturn $return): void
    {
        if ($return->status !== 'draft') {
            throw new RuntimeException("Retur {$return->return_no} sudah diposting.");
        }

        DB::transaction(function () use ($return) {
            $return->load('items.product', 'items.deliveryItem', 'customer', 'salesInvoice', 'deliveryOrder');
            $date = $return->date->toDateString();

            $costReturned = 0.0;
            $costDamaged = 0.0;

            foreach ($return->items as $item) {
                $quantity = (float) $item->quantity;

                if ($quantity <= 0) {
                    continue;
                }

                $unitCost = $this->returnUnitCost($return, $item);
                $item->forceFill(['unit_cost' => $unitCost])->save();
                $value = round($quantity * $unitCost, 2);

                if ($item->isDamaged()) {
                    // Tidak ada pergerakan stok: barangnya tidak layak dijual lagi.
                    $costDamaged += $value;
                } else {
                    $this->inventory->receive(
                        $item->product_id,
                        $return->warehouse_id,
                        $quantity,
                        $unitCost,
                        'sales_return',
                        $return->id,
                        $return->return_no,
                        $date,
                        'Retur dari '.$return->customer->name,
                    );

                    $costReturned += $value;
                }

                if ($item->delivery_order_item_id && $item->deliveryItem) {
                    $item->deliveryItem->increment('returned_qty', $quantity);
                }
            }

            $costReturned = round($costReturned, 2);
            $costDamaged = round($costDamaged, 2);

            $lines = [
                ['account_id' => $this->accounts->id('acc_inventory'), 'debit' => $costReturned],
                ['account_id' => $this->accounts->id('acc_damaged_goods'), 'debit' => $costDamaged],
                ['account_id' => $this->accounts->id('acc_cogs'), 'credit' => round($costReturned + $costDamaged, 2)],
            ];

            if ($return->hasCreditNote()) {
                $lines[] = ['account_id' => $this->accounts->id('acc_sales_return'), 'debit' => (float) $return->subtotal];
                $lines[] = ['account_id' => $this->accounts->id('acc_tax_output'), 'debit' => (float) $return->tax_amount];
                $lines[] = [
                    'account_id' => $this->accounts->id('acc_receivable'),
                    'credit' => (float) $return->total,
                    'partner_id' => $return->partner_id,
                ];
            }

            if (array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines)) > 0) {
                $this->journals->post(
                    $lines,
                    $date,
                    'sales',
                    'Retur penjualan '.$return->return_no,
                    $return,
                    $return->return_no,
                );
            }

            if ($return->hasCreditNote()) {
                $invoice = $return->salesInvoice;
                $invoice->increment('credit_amount', (float) $return->total);
                $invoice->refresh()->syncPaymentStatus();
            }

            $return->forceFill([
                'status' => 'posted',
                'posted_at' => now(),
                'cost_returned' => $costReturned,
                'cost_damaged' => $costDamaged,
            ])->save();

            $return->recordActivity('posted', "Retur {$return->return_no} diposting");
        });
    }

    public function cancelReturn(SalesReturn $return): void
    {
        if ($return->status !== 'posted') {
            throw new RuntimeException('Hanya retur posted yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($return) {
            $return->load('items.deliveryItem', 'salesInvoice');

            $this->inventory->reverseDocument('sales_return', $return->id, now()->toDateString());
            $this->journals->reverseForSource($return);

            foreach ($return->items as $item) {
                if ($item->delivery_order_item_id && $item->deliveryItem) {
                    $item->deliveryItem->decrement('returned_qty', (float) $item->quantity);
                }
            }

            if ($return->hasCreditNote() && $return->salesInvoice) {
                $invoice = $return->salesInvoice;
                $invoice->decrement('credit_amount', (float) $return->total);
                $invoice->refresh()->syncPaymentStatus();
            }

            $return->forceFill(['status' => 'cancelled'])->save();
            $return->recordActivity('cancelled', "Retur {$return->return_no} dibatalkan");
        });
    }

    /**
     * Harga pokok barang saat meninggalkan gudang lewat surat jalan asalnya;
     * bila retur tidak terhubung ke surat jalan, dipakai rata-rata berjalan.
     */
    private function returnUnitCost(SalesReturn $return, SalesReturnItem $item): float
    {
        if ($item->unit_cost > 0) {
            return (float) $item->unit_cost;
        }

        if ($return->delivery_order_id) {
            $cost = StockMovement::query()
                ->where('ref_type', 'delivery_order')
                ->where('ref_id', $return->delivery_order_id)
                ->where('product_id', $item->product_id)
                ->value('unit_cost');

            if ($cost > 0) {
                return (float) $cost;
            }
        }

        return $this->inventory->currentCost($item->product_id, $return->warehouse_id);
    }

    public function postInvoice(SalesInvoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw new RuntimeException("Faktur {$invoice->invoice_no} sudah diposting.");
        }

        DB::transaction(function () use ($invoice) {
            $invoice->load('items');
            $date = $invoice->date->toDateString();

            $this->journals->post(
                [
                    ['account_id' => $this->accounts->id('acc_receivable'), 'debit' => (float) $invoice->total, 'partner_id' => $invoice->partner_id],
                    ['account_id' => $this->accounts->id('acc_sales_discount'), 'debit' => (float) $invoice->discount_amount],
                    ['account_id' => $this->accounts->id('acc_sales'), 'credit' => (float) $invoice->subtotal],
                    ['account_id' => $this->accounts->id('acc_freight_out'), 'credit' => (float) $invoice->shipping_cost],
                    ['account_id' => $this->accounts->id('acc_tax_output'), 'credit' => (float) $invoice->tax_amount],
                ],
                $date,
                'sales',
                'Faktur penjualan '.$invoice->invoice_no,
                $invoice,
                $invoice->invoice_no,
            );

            $invoice->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
            $invoice->recordActivity('posted', "Faktur {$invoice->invoice_no} diposting");

            if ($invoice->salesOrder) {
                foreach ($invoice->items as $item) {
                    $invoice->salesOrder->items()
                        ->where('product_id', $item->product_id)
                        ->limit(1)
                        ->increment('invoiced_qty', (float) $item->quantity);
                }
            }
        });
    }

    public function cancelInvoice(SalesInvoice $invoice): void
    {
        if (! in_array($invoice->status, ['posted', 'partial'], true)) {
            throw new RuntimeException('Hanya faktur posted yang bisa dibatalkan.');
        }

        if ((float) $invoice->paid_amount > 0) {
            throw new RuntimeException('Batalkan penerimaan pembayaran terkait terlebih dahulu.');
        }

        DB::transaction(function () use ($invoice) {
            $this->journals->reverseForSource($invoice);
            $invoice->forceFill(['status' => 'cancelled'])->save();
            $invoice->recordActivity('cancelled', "Faktur {$invoice->invoice_no} dibatalkan");
        });
    }

    public function postPayment(CustomerPayment $payment): void
    {
        if ($payment->status !== 'draft') {
            throw new RuntimeException("Penerimaan {$payment->payment_no} sudah diposting.");
        }

        DB::transaction(function () use ($payment) {
            $payment->load('items.invoice');
            $date = $payment->date->toDateString();

            foreach ($payment->items as $item) {
                $invoice = $item->invoice;
                $invoice->increment('paid_amount', (float) $item->amount);
                $invoice->refresh()->syncPaymentStatus();
            }

            $this->journals->post(
                [
                    ['account_id' => $payment->account_id, 'debit' => (float) $payment->amount],
                    ['account_id' => $this->accounts->id('acc_receivable'), 'credit' => (float) $payment->amount, 'partner_id' => $payment->partner_id],
                ],
                $date,
                'cash',
                'Penerimaan dari '.$payment->customer->name,
                $payment,
                $payment->reference ?: $payment->payment_no,
            );

            $payment->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
            $payment->recordActivity('posted', "Penerimaan {$payment->payment_no} diposting");
        });
    }

    public function cancelPayment(CustomerPayment $payment): void
    {
        if ($payment->status !== 'posted') {
            throw new RuntimeException('Hanya penerimaan posted yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($payment) {
            $payment->load('items.invoice');

            foreach ($payment->items as $item) {
                $invoice = $item->invoice;
                $invoice->decrement('paid_amount', (float) $item->amount);
                $invoice->refresh()->syncPaymentStatus();
            }

            $this->journals->reverseForSource($payment);
            $payment->forceFill(['status' => 'cancelled'])->save();
            $payment->recordActivity('cancelled', "Penerimaan {$payment->payment_no} dibatalkan");
        });
    }
}
