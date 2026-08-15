<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DPP Nilai Lain (PMK 131/2024).
 *
 * Sejak 2025 tarif PPN 12%, tetapi untuk barang/jasa umum dasar pengenaannya
 * memakai "nilai lain" sebesar 11/12 dari harga jual, sehingga pajak terutang
 * setara 11% dari harga jual. Nilainya disimpan per dokumen — bukan dihitung
 * ulang saat ditampilkan — agar faktur lama tidak ikut berubah bila rasio
 * peraturannya suatu saat diubah.
 */
return new class extends Migration
{
    private const TABLES = [
        'quotations',
        'sales_orders',
        'sales_invoices',
        'purchase_orders',
        'purchase_invoices',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('dpp_other_amount', 18, 2)->default(0)->after('subtotal');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('dpp_other_amount');
            });
        }
    }
};
