<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_designer', function (Blueprint $table) {
            // Drop foreign keys first to avoid constraint errors
            $table->dropForeign(['client_id']);
            $table->dropForeign(['designer_id']);

            // حذف القيد الفريد القديم على (client_id, designer_id)
            $table->dropUnique(['client_id', 'designer_id']);

            // إضافة قيد فريد جديد على (client_id, week_start_date)
            // هذا يسمح لنفس العميل بالعمل مع مصممين مختلفين في أسابيع مختلفة
            // لكن يمنع تعيين نفس العميل لأكثر من مصمم في نفس الأسبوع
            $table->unique(['client_id', 'week_start_date'], 'client_week_unique');

            // Restore foreign keys
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('designer_id')->references('id')->on('designers')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_designer', function (Blueprint $table) {
            // Drop foreign keys
            $table->dropForeign(['client_id']);
            $table->dropForeign(['designer_id']);

            // إعادة القيد القديم
            $table->dropUnique('client_week_unique');
            $table->unique(['client_id', 'designer_id']);

            // Restore foreign keys
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('designer_id')->references('id')->on('designers')->cascadeOnDelete();
        });
    }
};
