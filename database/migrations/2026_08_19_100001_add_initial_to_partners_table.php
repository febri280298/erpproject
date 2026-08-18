<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode inisial mitra — singkatan pendek dari namanya, misalnya
 * "PT Ravalia Inti Mandiri" menjadi RIM.
 *
 * Berbeda dari kolom `code` yang berurutan (CUST-001) dan dipakai sistem,
 * inisial ini yang dipakai orang saat berbicara dan menulis catatan.
 *
 * Nullable supaya mitra lama tidak perlu langsung diisi, tetapi unik ketika
 * terisi — inisial yang dipakai dua mitra sekaligus justru menghilangkan
 * gunanya sebagai penanda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('initial', 10)->nullable()->after('code');
            $table->unique('initial');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropUnique(['initial']);
            $table->dropColumn('initial');
        });
    }
};
