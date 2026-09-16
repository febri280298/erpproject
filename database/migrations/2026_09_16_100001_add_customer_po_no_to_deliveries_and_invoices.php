<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor PO customer ikut sampai ke surat jalan dan faktur.
 *
 * Pesanan penjualan sudah menyimpannya sejak awal, tetapi bagian penerimaan
 * customer mencocokkan surat jalan dan faktur terhadap PO mereka sendiri —
 * bukan terhadap nomor SO kita. Tanpa nomor itu tercetak, dokumen sering
 * dikembalikan hanya untuk ditanyakan "ini PO yang mana".
 *
 * Disalin, bukan dibaca lewat relasi ke SO. Dua alasan: surat jalan dan faktur
 * bisa dibuat tanpa pesanan penjualan sama sekali, dan nomor yang tercetak di
 * dokumen yang sudah dikirim ke customer tidak boleh berubah hanya karena
 * seseorang menyunting SO-nya belakangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('customer_po_no', 60)->nullable()->after('sales_order_id');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('customer_po_no', 60)->nullable()->after('sales_order_id');
        });

        // Dokumen yang sudah telanjur dibuat diisi dari pesanan penjualannya,
        // supaya cetak ulang dokumen lama ikut menampilkan nomor PO-nya.
        foreach (['delivery_orders', 'sales_invoices'] as $tabel) {
            \Illuminate\Support\Facades\DB::statement("
                UPDATE {$tabel} d
                JOIN sales_orders so ON so.id = d.sales_order_id
                SET d.customer_po_no = so.customer_po_no
                WHERE d.sales_order_id IS NOT NULL
                  AND so.customer_po_no IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn('customer_po_no');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn('customer_po_no');
        });
    }
};
