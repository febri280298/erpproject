<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->string('bom_no', 40)->unique();
            $table->string('name', 150);
            $table->foreignId('product_id')->constrained();      // finished good
            $table->decimal('quantity', 15, 4)->default(1);      // output per batch
            $table->foreignId('uom_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('product_id');
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();      // component
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('waste_percent', 8, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 40)->unique();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->foreignId('bom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('produced_qty', 15, 4)->default(0);
            $table->decimal('material_cost', 18, 2)->default(0);
            $table->decimal('overhead_cost', 18, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft|released|in_progress|completed|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
        });

        Schema::create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();      // component consumed
            $table->decimal('planned_qty', 15, 4)->default(0);
            $table->decimal('consumed_qty', 15, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_items');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('boms');
    }
};
