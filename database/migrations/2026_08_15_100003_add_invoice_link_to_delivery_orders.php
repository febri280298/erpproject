<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menautkan surat jalan ke faktur yang menagihnya.
 *
 * Satu faktur boleh mencakup banyak surat jalan (kirim beberapa kali, tagih
 * sekali), tetapi satu surat jalan hanya boleh ditagih oleh satu faktur —
 * karena itu tautannya disimpan di sisi surat jalan, bukan tabel pivot.
 * Kolom ini sekaligus menjadi penjaga agar tidak ada pengiriman yang tertagih
 * dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->foreignId('sales_invoice_id')->nullable()->after('sales_order_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_invoice_id');
        });
    }
};
