<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Key/value application settings (company profile, accounting defaults, ...)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('general');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string|number|boolean|json
            $table->timestamps();

            $table->index('group');
        });

        // Per-module document numbering (PO/2026/08/0001)
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('prefix', 20);
            $table->string('reset_period', 10)->default('yearly'); // never|yearly|monthly
            $table->unsignedInteger('padding')->default(4);
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->unsignedTinyInteger('period_month')->nullable();
            $table->timestamps();

            $table->unique('module');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 30);              // created|updated|deleted|posted|approved|login
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('avatar');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'avatar', 'is_active', 'last_login_at']);
        });

        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('settings');
    }
};
