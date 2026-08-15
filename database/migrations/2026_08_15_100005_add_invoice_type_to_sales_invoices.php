<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipe faktur penjualan beserta potongan PPh 23 untuk faktur jasa.
 *
 * `wht` = withholding tax. Pada faktur jasa, customer memotong PPh 23 dari
 * nilai jasa (di luar PPN) dan menyetorkannya sendiri, sehingga yang benar-benar
 * kami terima lebih kecil dari total faktur. Potongan itu bukan beban melainkan
 * kredit pajak, karena itu dicatat sebagai uang muka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('invoice_type', 10)->default('ppn')->after('invoice_no'); // ppn|non_ppn|jasa
            $table->decimal('wht_rate', 8, 4)->default(0)->after('tax_amount');
            $table->decimal('wht_amount', 18, 2)->default(0)->after('wht_rate');

            $table->index('invoice_type');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropIndex(['invoice_type']);
            $table->dropColumn(['invoice_type', 'wht_rate', 'wht_amount']);
        });
    }
};
