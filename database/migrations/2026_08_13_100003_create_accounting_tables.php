<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of accounts. Only `is_postable` leaves may carry journal lines.
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('type', 20); // asset|liability|equity|revenue|expense
            $table->string('subtype', 40)->nullable(); // cash|bank|receivable|payable|inventory|...
            $table->string('normal_balance', 10)->default('debit'); // debit|credit
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('journal_no', 40)->unique();
            $table->date('date');
            $table->string('type', 20)->default('general'); // general|sales|purchase|cash|inventory|payroll
            $table->string('reference', 100)->nullable();
            $table->string('description', 255)->nullable();
            // Polymorphic link back to the document that generated this journal.
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);
            $table->string('status', 20)->default('posted'); // draft|posted|reversed
            $table->foreignId('reversed_by')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['date', 'status']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['account_id', 'journal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('accounts');
    }
};
