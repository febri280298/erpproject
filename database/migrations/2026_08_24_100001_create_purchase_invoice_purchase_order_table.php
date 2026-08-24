<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu faktur pembelian bisa menagih beberapa pesanan pembelian sekaligus.
 *
 * Supplier sering mengirim satu tagihan untuk beberapa PO yang dikirim dalam
 * periode yang sama. Kolom `purchase_order_id` di header hanya memuat satu,
 * jadi hubungannya dipindah ke tabel penghubung.
 *
 * Kolom lama tetap dipertahankan, tidak dihapus: faktur yang sudah ada memakai
 * kolom itu, dan menghapusnya berarti memutus jejak dokumen yang sudah jadi.
 * Barisnya disalin ke tabel baru supaya keduanya bercerita hal yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_purchase_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();

            $table->unique(['purchase_invoice_id', 'purchase_order_id'], 'pi_po_unik');
        });

        // Faktur lama ikut tercatat di tabel penghubung agar kode baru tidak
        // perlu memeriksa dua tempat setiap kali menelusuri asal faktur.
        DB::table('purchase_invoices')
            ->whereNotNull('purchase_order_id')
            ->orderBy('id')
            ->chunkById(200, function ($faktur) {
                DB::table('purchase_invoice_purchase_order')->insertOrIgnore(
                    $faktur->map(fn ($f) => [
                        'purchase_invoice_id' => $f->id,
                        'purchase_order_id' => $f->purchase_order_id,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_purchase_order');
    }
};
