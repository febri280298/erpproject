<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retur penjualan berikut nota kreditnya.
 *
 * Berbeda dari membatalkan surat jalan: pengiriman tetap tercatat terjadi,
 * retur berdiri sebagai dokumen sendiri dengan tanggal dan jumlahnya sendiri,
 * dan boleh sebagian.
 *
 * `sales_invoices.credit_amount` menampung nilai nota kredit yang menempel pada
 * sebuah faktur, sehingga sisa tagihan berkurang tanpa perlu memalsukannya
 * sebagai pembayaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 40)->unique();
            $table->date('date');
            $table->foreignId('delivery_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('reason', 150)->nullable();

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            // Harga pokok barang yang benar-benar kembali ke gudang.
            $table->decimal('cost_returned', 18, 2)->default(0);
            // Harga pokok barang rusak yang tidak masuk stok lagi.
            $table->decimal('cost_damaged', 18, 2)->default(0);

            $table->boolean('issue_credit_note')->default(true);
            $table->string('status', 20)->default('draft'); // draft|posted|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
            $table->index('partner_id');
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();

            $table->decimal('quantity', 15, 4)->default(0);
            $table->string('condition', 10)->default('good');   // good|damaged
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            // Diisi saat posting, dari harga pokok pengiriman aslinya.
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->decimal('credit_amount', 18, 2)->default(0)->after('paid_amount');
        });

        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->decimal('returned_qty', 15, 4)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropColumn('returned_qty');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn('credit_amount');
        });

        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
