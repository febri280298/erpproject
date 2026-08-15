<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-customer sale price. Overrides the customer's price tier, so a negotiated
 * deal for one buyer does not require creating a tier just for them.
 *
 * Resolution order when quoting: harga khusus customer → tingkat harga customer
 * → tingkat default → harga jual dasar produk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_customer_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 18, 2)->default(0);
            $table->decimal('min_qty', 15, 4)->default(0);
            $table->string('notes', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_customer_prices');
    }
};
