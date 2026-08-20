<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DPP Nilai Lain per baris.
 *
 * Nilainya sudah dihitung per baris sejak awal — dasar pengenaan pajak tiap
 * baris adalah rasio (11/12) dari harga setelah diskon — tetapi yang tersimpan
 * hanya jumlah totalnya di header dokumen. Akibatnya faktur tidak bisa
 * menunjukkan dasar pengenaan tiap item, padahal itu yang diperiksa saat
 * mencocokkan faktur pajak per baris.
 *
 * Disimpan, bukan dihitung ulang saat ditampilkan, dengan alasan yang sama
 * seperti kolom di header: bila rasio peraturannya berubah, faktur lama harus
 * tetap menunjukkan angka yang dulu dipakai.
 *
 * Baris yang sudah ada diisi dari subtotalnya sendiri — dokumen lama dibuat
 * ketika DPP Nilai Lain belum aktif, jadi dasar pengenaannya memang sama
 * dengan DPP biasa. Mengisinya dengan 0 akan membuat faktur lama seolah tidak
 * punya dasar pengenaan sama sekali.
 */
return new class extends Migration
{
    private const TABLES = [
        'quotation_items',
        'sales_order_items',
        'sales_invoice_items',
        'purchase_order_items',
        'purchase_invoice_items',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('dpp_other', 18, 2)->default(0)->after('subtotal');
            });

            DB::table($table)->update(['dpp_other' => DB::raw('subtotal')]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('dpp_other');
            });
        }
    }
};
