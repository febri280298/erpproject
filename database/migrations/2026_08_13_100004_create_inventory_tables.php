<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Current on-hand per product/warehouse, plus moving-average cost.
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('reserved_qty', 15, 4)->default(0);
            $table->decimal('avg_cost', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });

        // Append-only ledger — every stock change writes exactly one row here.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('direction', 3); // in|out
            $table->string('ref_type', 50);  // goods_receipt|delivery_order|adjustment|transfer|production
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('ref_no', 50)->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('balance_qty', 15, 4)->default(0);
            $table->decimal('balance_value', 18, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'date']);
            $table->index(['ref_type', 'ref_id']);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no', 40)->unique();
            $table->date('date');
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->string('status', 20)->default('draft'); // draft|posted|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 15, 4);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        // Covers both manual corrections and stock-opname results.
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_no', 40)->unique();
            $table->date('date');
            $table->foreignId('warehouse_id')->constrained();
            $table->string('reason', 150)->nullable();
            $table->string('status', 20)->default('draft'); // draft|posted|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('system_qty', 15, 4)->default(0);
            $table->decimal('actual_qty', 15, 4)->default(0);
            $table->decimal('difference', 15, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
    }
};
