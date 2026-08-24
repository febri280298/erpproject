<?php

namespace App\Services\Posting;

use App\Models\Purchasing\GoodsReceipt;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\SupplierPayment;
use App\Services\AccountMap;
use App\Services\InventoryService;
use App\Services\JournalService;
use App\Services\PricingService;
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
        private readonly PricingService $pricing,
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

                // Keep the supplier price list honest: record what was actually paid.
                if ($item->product) {
                    $this->pricing->noteReceiptCost(
                        $item->product,
                        $receipt->partner_id,
                        (float) $item->unit_price,
                        $date,
                    );
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

            $this->allocateInvoicedQty($invoice);
        });
    }

    /**
     * Mengembalikan kuantitas tertagih saat faktur dibatalkan.
     *
     * Cerminan dari allocateInvoicedQty, dan urutannya sengaja dibalik: yang
     * terakhir dialokasikan dilepas lebih dulu, sehingga posting lalu batal
     * mengembalikan keadaan persis seperti semula.
     *
     * Tanpa ini pembatalan hanya membalik jurnalnya. Pesanan tetap tercatat
     * sudah ditagih padahal fakturnya batal, dan tidak akan pernah bisa
     * ditagih lagi — tersangkut tanpa pesan apa pun.
     */
    private function releaseInvoicedQty(PurchaseInvoice $invoice): void
    {
        $orders = $invoice->purchaseOrders()->with('items')->orderBy('date')->orderBy('id')->get();

        if ($orders->isEmpty() && $invoice->purchaseOrder) {
            $orders = collect([$invoice->purchaseOrder->load('items')]);
        }

        if ($orders->isEmpty()) {
            return;
        }

        foreach ($invoice->items as $line) {
            $sisaFaktur = (float) $line->quantity;

            foreach ($orders->reverse() as $order) {
                if ($sisaFaktur <= 0) {
                    break;
                }

                foreach ($order->items->reverse() as $orderItem) {
                    if ($sisaFaktur <= 0) {
                        break;
                    }

                    if ($orderItem->product_id !== $line->product_id) {
                        continue;
                    }

                    // Tidak boleh melepas lebih dari yang pernah tercatat:
                    // invoiced_qty negatif membuat sisa tagihan melebihi jumlah
                    // yang dipesan.
                    $terpakai = min((float) $orderItem->invoiced_qty, $sisaFaktur);

                    if ($terpakai <= 0) {
                        continue;
                    }

                    $orderItem->decrement('invoiced_qty', $terpakai);
                    $sisaFaktur -= $terpakai;
                }
            }
        }
    }

    /**
     * Membagi kuantitas yang ditagih ke pesanan-pesanan asalnya.
     *
     * Satu faktur bisa menagih beberapa PO sekaligus, dan produk yang sama bisa
     * muncul di lebih dari satu PO. Alokasinya karena itu dijalankan berurutan
     * dari pesanan tertua: sisa tiap baris PO diisi sampai penuh sebelum pindah
     * ke pesanan berikutnya — pesanan yang lebih dulu dibuat semestinya lebih
     * dulu tuntas.
     *
     * Versi sebelumnya menaikkan invoiced_qty pada baris PO pertama yang
     * produknya cocok tanpa memeriksa sisanya. Dengan satu PO saja itu sudah
     * bisa melebihi jumlah pesanan; dengan beberapa PO, angkanya menempel pada
     * pesanan yang salah dan membuat pesanan lain tampak belum tertagih.
     */
    private function allocateInvoicedQty(PurchaseInvoice $invoice): void
    {
        $orders = $invoice->purchaseOrders()->with('items')->orderBy('date')->orderBy('id')->get();

        // Faktur lama hanya punya kolom tunggalnya.
        if ($orders->isEmpty() && $invoice->purchaseOrder) {
            $orders = collect([$invoice->purchaseOrder->load('items')]);
        }

        if ($orders->isEmpty()) {
            return;
        }

        foreach ($invoice->items as $line) {
            $sisaFaktur = (float) $line->quantity;

            foreach ($orders as $order) {
                if ($sisaFaktur <= 0) {
                    break;
                }

                foreach ($order->items as $orderItem) {
                    if ($sisaFaktur <= 0) {
                        break;
                    }

                    if ($orderItem->product_id !== $line->product_id) {
                        continue;
                    }

                    $ruang = $orderItem->uninvoicedQty();

                    if ($ruang <= 0) {
                        continue;
                    }

                    $porsi = min($ruang, $sisaFaktur);
                    $orderItem->increment('invoiced_qty', $porsi);
                    $sisaFaktur -= $porsi;
                }
            }
        }
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
            $this->releaseInvoicedQty($invoice);
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
