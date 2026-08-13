<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no', 40)->unique();
            $table->date('date');
            $table->date('valid_until')->nullable();
            $table->foreignId('partner_id')->constrained();          // customer
            $table->string('status', 20)->default('draft'); // draft|sent|accepted|rejected|expired|closed
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('shipping_cost', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'status']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('so_no', 40)->unique();
            $table->date('date');
            $table->date('delivery_date')->nullable();
            $table->foreignId('partner_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_po_no', 60)->nullable();
            $table->string('status', 20)->default('draft'); // draft|confirmed|partial|delivered|closed|cancelled
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('shipping_cost', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
            $table->index('partner_id');
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('delivered_qty', 15, 4)->default(0);
            $table->decimal('invoiced_qty', 15, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('do_no', 40)->unique();
            $table->date('date');
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('driver_name', 100)->nullable();
            $table->string('vehicle_no', 30)->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('status', 20)->default('draft'); // draft|posted|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
        });

        Schema::create('delivery_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 40)->unique();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->foreignId('partner_id')->constrained();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('shipping_cost', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft|posted|partial|paid|cancelled
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
            $table->index('partner_id');
        });

        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 40)->unique();
            $table->date('date');
            $table->foreignId('partner_id')->constrained();
            $table->foreignId('account_id')->constrained();  // cash / bank account received into
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('method', 30)->default('transfer');
            $table->string('reference', 100)->nullable();
            $table->string('status', 20)->default('draft'); // draft|posted|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_id')->constrained();
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_items');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('sales_invoice_items');
        Schema::dropIfExists('sales_invoices');
        Schema::dropIfExists('delivery_order_items');
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
