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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            $table->boolean('status')->default(true);

            // المعلومات الأساسية
            $table->string('company');
            $table->string('client_name')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->onDelete('set null');
            $table->string('address')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('contact_job')->nullable();
            // $table->timestamp('date_of_subscription')->useCurrent();
            // $table->foreignId('currency_id')->constrained()->onDelete('restrict');
            // $table->decimal('amount', 10, 2);
            $table->decimal('marketing_amount', 10, 2)->nullable();
            // $table->enum('subscription_type', ['monthly', 'annual'])->default('monthly');
            // $table->enum('payment_type', ['advance', 'deferred'])->default('advance');

            // الإشعار
            $table->timestamp('notified_at')->nullable();

            // التوقيف
            $table->integer('suspension_days')->nullable();
            $table->boolean('is_credit_allowed')->default(false);
            $table->timestamp('suspended_at')->nullable();

            // العلاقات الأخرى
            // $table->foreignId('package_id')->constrained()->onDelete('restrict');
            // $table->foreignId('client_need_id')->constrained()->onDelete('restrict');
            $table->foreignId('category_id')->constrained()->onDelete('restrict');

            // التقييم
            // $table->enum('customer_rating_type', ['automatic', 'manual'])->default('automatic');
            $table->unsignedTinyInteger('customer_rating_value')->nullable();
            $table->integer('rating')->nullable();

            // الكليشة
            $table->integer('change_cliche_threshold')->nullable();

            // التوقيتات العامة
            $table->timestamps();
            $table->foreignId('added_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
