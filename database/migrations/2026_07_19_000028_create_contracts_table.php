<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->string('payment_type');
            $table->string('billing_cycle');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('weekly_designs_count');
            $table->integer('monthly_designs_count');
            $table->datetime('notification_date')->nullable();
            $table->boolean('auto_suspension_enabled')->default(false);
            $table->integer('suspension_period_days')->nullable();
            $table->decimal('total_amount', 15, 2);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('marketing_amount', 15, 2)->nullable();
            $table->boolean('auto_renewal')->default(false);
            $table->text('legal_notes')->nullable();
            $table->integer('grace_period_days')->nullable();
            $table->boolean('is_under_lawsuit')->default(false);
            $table->boolean('additional_designs_enabled')->default(false);
            $table->decimal('additional_design_price', 10, 2)->nullable();
            $table->boolean('simple_requests_enabled')->default(false);
            $table->decimal('simple_request_price', 10, 2)->nullable();
            $table->integer('simple_requests_count')->default(0);
            $table->integer('additional_designs_count')->default(0);
            $table->softDeletes();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
