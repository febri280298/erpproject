<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-price support:
 *   price_levels            — named sale tiers (Eceran, Grosir, Proyek…), max 10
 *   product_prices          — one sale price per product per tier
 *   product_supplier_prices — one purchase price per product per supplier
 *   price_histories         — append-only audit of every price change
 *
 * `products.purchase_price` / `sale_price` stay as the fallback used when a
 * product has no tier or supplier row yet, so nothing breaks for simple items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_level_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 18, 2)->default(0);
            $table->decimal('min_qty', 15, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'price_level_id']);
        });

        Schema::create('product_supplier_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 18, 2)->default(0);
            $table->string('supplier_sku', 60)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->decimal('min_order_qty', 15, 4)->default(0);
            $table->boolean('is_preferred')->default(false);
            $table->date('last_purchased_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'partner_id']);
            $table->index(['product_id', 'is_preferred']);
        });

        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('price_type', 10);                     // purchase | sale
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('price_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 100);                         // "Grosir" / "PT Baja Perkasa"
            $table->decimal('old_price', 18, 2)->default(0);
            $table->decimal('new_price', 18, 2)->default(0);
            $table->decimal('difference', 18, 2)->default(0);
            $table->decimal('percent', 8, 2)->default(0);
            $table->string('source', 30)->default('manual');      // manual | import | receipt
            $table->string('notes', 255)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'price_type', 'created_at']);
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->foreignId('price_level_id')->nullable()->after('payment_term_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_level_id');
        });

        Schema::dropIfExists('price_histories');
        Schema::dropIfExists('product_supplier_prices');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('price_levels');
    }
};
