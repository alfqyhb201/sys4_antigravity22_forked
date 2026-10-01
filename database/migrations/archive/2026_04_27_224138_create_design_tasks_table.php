<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الـ Migration لإنشاء جدول مهام التصميم.
     */
    public function up(): void
    {
        Schema::create('design_tasks', function (Blueprint $table) {
            $table->id();

            // الموظف الموكل (المصمم)
            $table->foreignId('designer_id')
                ->constrained('designers')
                ->cascadeOnDelete();

            // المسؤول الذي أنشأ المهمة
            $table->foreignId('assigner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // هل خاصة بعميل مشترك
            $table->boolean('is_subscribed_client')->default(false);

            // العميل المشترك (اختياري)
            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            // اسم العميل (نصي - في حالة عميل غير مشترك)
            $table->string('client_name')->nullable();

            // وصف المهمة
            $table->text('description')->nullable();

            // ملفات مرجعية مرفقة من المدير
            $table->json('reference_files')->nullable();

            // بإضافي (نعم/لا)
            $table->boolean('is_extra')->default(false);

            // المبلغ الإضافي
            $table->decimal('amount', 10, 2)->nullable();

            // الأهمية
            $table->string('priority')->default('medium');

            // هل ينخصم من رصيد العميل (للعملاء المشتركين فقط)
            $table->boolean('deduct_from_balance')->default(false);

            // حالة المهمة
            $table->string('status')->default('pending');

            // التصاميم المرفوعة من المصمم
            $table->json('design_files')->nullable();

            // ملاحظات التعديل من المدير
            $table->text('revision_notes')->nullable();

            // وقت تسليم المصمم
            $table->timestamp('submitted_at')->nullable();

            // التواريخ
            $table->timestamps();
        });
    }

    /**
     * التراجع عن الـ Migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('design_tasks');
    }
};
