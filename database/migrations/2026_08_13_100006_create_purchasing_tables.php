<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('pr_no', 40)->unique();
            $table->date('date');
            $table->date('required_date')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft|submitted|approved|rejected|closed
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
        });

        Schema::create('purchase_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('ordered_qty', 15, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_no', 40)->unique();
            $table->date('date');
            $table->date('expected_date')->nullable();
            $table->foreignId('partner_id')->constrained();          // supplier
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft|approved|partial|received|closed|cancelled
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

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('received_qty', 15, 4)->default(0);
            $table->decimal('invoiced_qty', 15, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('grn_no', 40)->unique();
            $table->date('date');
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('supplier_do_no', 60)->nullable();
            $table->string('status', 20)->default('draft'); // draft|posted|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 40)->unique();
            $table->string('supplier_invoice_no', 60)->nullable();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->foreignId('partner_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('shipping_cost', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft|posted|partial|paid|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
            $table->index('partner_id');
        });

        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
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

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 40)->unique();
            $table->date('date');
            $table->foreignId('partner_id')->constrained();
            $table->foreignId('account_id')->constrained();  // cash / bank account paid from
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('method', 30)->default('transfer'); // cash|transfer|cheque|giro
            $table->string('reference', 100)->nullable();
            $table->string('status', 20)->default('draft'); // draft|posted|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained();
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_items');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_invoice_items');
        Schema::dropIfExists('purchase_invoices');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_requisition_items');
        Schema::dropIfExists('purchase_requisitions');
    }
};
