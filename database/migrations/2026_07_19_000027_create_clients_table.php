<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->boolean('status')->default(true);
            $table->string('company');
            $table->string('client_name')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('contact_job')->nullable();
            $table->decimal('marketing_amount', 10, 2)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->integer('suspension_days')->nullable();
            $table->boolean('is_credit_allowed')->default(false);
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->tinyInteger('customer_rating_value')->unsigned()->nullable();
            $table->integer('rating')->nullable();
            $table->integer('change_cliche_threshold')->nullable();
            $table->foreignId('fixed_designer_id')->nullable()->constrained('designers')->nullOnDelete();
            $table->integer('additional_designs_balance')->default(0);
            $table->boolean('is_pay_per_design')->default(false);
            $table->text('notes')->nullable();
            $table->integer('cliche_counter')->default(0);
            $table->boolean('has_generate_feature')->default(true);
            $table->json('generate_types')->nullable();
            $table->boolean('enable_very_high')->default(true);
            $table->json('importance_weights')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('design_data')->nullable();
            $table->decimal('wallet_balance', 15, 2)->default(0.00);
            $table->softDeletes();
            $table->foreignId('added_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
