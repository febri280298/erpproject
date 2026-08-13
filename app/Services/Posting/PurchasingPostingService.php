<?php

namespace App\Services\Posting;

use App\Models\Purchasing\GoodsReceipt;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\SupplierPayment;
use App\Services\AccountMap;
use App\Services\InventoryService;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Posts and un-posts the procure-to-pay documents.
 *
 * Accounting flow:
 *   GRN       Dr Persediaan            Cr Penerimaan Belum Ditagih (GRNI)
 *   Invoice   Dr GRNI / Persediaan     Cr Utang Usaha
 *             Dr PPN Masukan
 *             Dr Beban Angkut          Cr Potongan Pembelian
 *   Payment   Dr Utang Usaha           Cr Kas/Bank
 */
class PurchasingPostingService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly JournalService $journals,
        private readonly AccountMap $accounts,
    ) {}

    public function postGoodsReceipt(GoodsReceipt $receipt): void
    {
        if ($receipt->status !== 'draft') {
            throw new RuntimeException("Penerimaan {$receipt->grn_no} sudah diposting.");
        }

        DB::transaction(function () use ($receipt) {
            $receipt->load('items.product', 'purchaseOrder.items');
            $date = $receipt->date->toDateString();
            $goodsValue = 0.0;

            foreach ($receipt->items as $item) {
                $this->inventory->receive(
                    $item->product_id,
                    $receipt->warehouse_id,
                    (float) $item->quantity,
                    (float) $item->unit_price,
                    'goods_receipt',
                    $receipt->id,
                    $receipt->grn_no,
                    $date,
                    'Penerimaan dari '.$receipt->supplier->name,
                );

                if ($item->product?->isStockable()) {
                    $goodsValue += $item->lineTotal();
                }

                // Roll the received quantity up to the originating PO line.
                if ($item->purchase_order_item_id && $item->orderItem) {
                    $item->orderItem->increment('received_qty', (float) $item->quantity);
                }
            }

            if ($goodsValue > 0) {
                $this->journals->post(
                    [
                        ['account_id' => $this->accounts->id('acc_inventory'), 'debit' => $goodsValue],
                        ['account_id' => $this->accounts->id('acc_grni'), 'credit' => $goodsValue, 'partner_id' => $receipt->partner_id],
                    ],
                    $date,
                    'inventory',
                    'Penerimaan barang '.$receipt->grn_no,
                    $receipt,
                    $receipt->grn_no,
                );
            }

            $receipt->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
            $receipt->recordActivity('posted', "Penerimaan {$receipt->grn_no} diposting");

            $receipt->purchaseOrder?->fresh()->load('items')->syncReceiptStatus();
        });
    }

    public function cancelGoodsReceipt(GoodsReceipt $receipt): void
    {
        if ($receipt->status !== 'posted') {
            throw new RuntimeException('Hanya penerimaan berstatus posted yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($receipt) {
            $receipt->load('items');

            $this->inventory->reverseDocument('goods_receipt', $receipt->id, now()->toDateString());
            $this->journals->reverseForSource($receipt);

            foreach ($receipt->items as $item) {
                if ($item->purchase_order_item_id && $item->orderItem) {
                    $item->orderItem->decrement('received_qty', (float) $item->quantity);
                }
            }

            $receipt->forceFill(['status' => 'cancelled'])->save();
            $receipt->recordActivity('cancelled', "Penerimaan {$receipt->grn_no} dibatalkan");

            $receipt->purchaseOrder?->fresh()->load('items')->syncReceiptStatus();
        });
    }

    public function postInvoice(PurchaseInvoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw new RuntimeException("Faktur {$invoice->invoice_no} sudah diposting.");
        }

        DB::transaction(function () use ($invoice) {
            $invoice->load('items.product');
            $date = $invoice->date->toDateString();

            // Goods already carried into inventory by a GRN clear the GRNI
            // account; a standalone invoice debits inventory directly.
            $goodsAccount = $invoice->purchase_order_id
                ? $this->accounts->id('acc_grni')
                : $this->accounts->id('acc_inventory');

            $lines = [
                ['account_id' => $goodsAccount, 'debit' => (float) $invoice->subtotal, 'partner_id' => $invoice->partner_id],
                ['account_id' => $this->accounts->id('acc_tax_input'), 'debit' => (float) $invoice->tax_amount],
                ['account_id' => $this->accounts->id('acc_freight_in'), 'debit' => (float) $invoice->shipping_cost],
                ['account_id' => $this->accounts->id('acc_purchase_discount'), 'credit' => (float) $invoice->discount_amount],
                ['account_id' => $this->accounts->id('acc_payable'), 'credit' => (float) $invoice->total, 'partner_id' => $invoice->partner_id],
            ];

            $this->journals->post(
                $lines,
                $date,
                'purchase',
                'Faktur pembelian '.$invoice->invoice_no,
                $invoice,
                $invoice->supplier_invoice_no ?: $invoice->invoice_no,
            );

            $invoice->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
            $invoice->recordActivity('posted', "Faktur {$invoice->invoice_no} diposting");

            if ($invoice->purchaseOrder) {
                foreach ($invoice->items as $item) {
                    $invoice->purchaseOrder->items()
                        ->where('product_id', $item->product_id)
                        ->limit(1)
                        ->increment('invoiced_qty', (float) $item->quantity);
                }
            }
        });
    }

    public function cancelInvoice(PurchaseInvoice $invoice): void
    {
        if (! in_array($invoice->status, ['posted', 'partial'], true)) {
            throw new RuntimeException('Hanya faktur posted yang bisa dibatalkan.');
        }

        if ((float) $invoice->paid_amount > 0) {
            throw new RuntimeException('Batalkan pembayaran terkait terlebih dahulu.');
        }

        DB::transaction(function () use ($invoice) {
            $this->journals->reverseForSource($invoice);
            $invoice->forceFill(['status' => 'cancelled'])->save();
            $invoice->recordActivity('cancelled', "Faktur {$invoice->invoice_no} dibatalkan");
        });
    }

    public function postPayment(SupplierPayment $payment): void
    {
        if ($payment->status !== 'draft') {
            throw new RuntimeException("Pembayaran {$payment->payment_no} sudah diposting.");
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
                    ['account_id' => $this->accounts->id('acc_payable'), 'debit' => (float) $payment->amount, 'partner_id' => $payment->partner_id],
                    ['account_id' => $payment->account_id, 'credit' => (float) $payment->amount],
                ],
                $date,
                'cash',
                'Pembayaran ke '.$payment->supplier->name,
                $payment,
                $payment->reference ?: $payment->payment_no,
            );

            $payment->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
            $payment->recordActivity('posted', "Pembayaran {$payment->payment_no} diposting");
        });
    }

    public function cancelPayment(SupplierPayment $payment): void
    {
        if ($payment->status !== 'posted') {
            throw new RuntimeException('Hanya pembayaran posted yang bisa dibatalkan.');
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
            $payment->recordActivity('cancelled', "Pembayaran {$payment->payment_no} dibatalkan");
        });
    }
}
