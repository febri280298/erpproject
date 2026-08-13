<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            // FK added after `employees` exists (mutual reference).
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_salary', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 30)->unique();
            $table->string('name', 150);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gender', 10)->nullable(); // L|P
            $table->date('birth_date')->nullable();
            $table->date('join_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->string('employment_type', 20)->default('permanent'); // permanent|contract|intern
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('npwp', 30)->nullable();
            $table->decimal('basic_salary', 18, 2)->default(0);
            $table->decimal('allowance', 18, 2)->default(0);
            $table->string('photo')->nullable();
            $table->string('status', 20)->default('active'); // active|resigned|terminated
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'department_id']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->string('status', 20)->default('present'); // present|late|absent|leave|sick|holiday
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index('date');
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('max_days')->default(12);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->string('leave_no', 40)->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 6, 2)->default(1);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|cancelled
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_no', 40)->unique();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->date('payment_date')->nullable();
            $table->decimal('total_gross', 18, 2)->default(0);
            $table->decimal('total_deduction', 18, 2)->default(0);
            $table->decimal('total_net', 18, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft|approved|paid|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['period_year', 'period_month']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->decimal('basic_salary', 18, 2)->default(0);
            $table->decimal('allowance', 18, 2)->default(0);
            $table->decimal('overtime', 18, 2)->default(0);
            $table->decimal('bonus', 18, 2)->default(0);
            $table->decimal('gross_salary', 18, 2)->default(0);
            $table->decimal('bpjs', 18, 2)->default(0);
            $table->decimal('tax_pph21', 18, 2)->default(0);
            $table->decimal('other_deduction', 18, 2)->default(0);
            $table->decimal('total_deduction', 18, 2)->default(0);
            $table->decimal('net_salary', 18, 2)->default(0);
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['payroll_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendances');

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
