<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration لإضافة حقول ميزة الجنريت لجدول العملاء.
 *
 * has_generate_feature: هل لدى العميل ميزة الجنريت (افتراضي: نعم).
 * generate_types: أنواع الجنريت المتاحة مخزنة كـ JSON.
 */
return new class extends Migration
{
    /**
     * تنفيذ الـ Migration.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->boolean('has_generate_feature')->default(true)->after('is_pay_per_design');
            $table->json('generate_types')->nullable()->after('has_generate_feature');
        });
    }

    /**
     * التراجع عن الـ Migration.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn(['has_generate_feature', 'generate_types']);
        });
    }
};
