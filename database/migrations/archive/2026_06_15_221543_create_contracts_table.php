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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active'); // active, suspended, expired
            $table->string('payment_type'); // advance, deferred
            $table->string('billing_cycle'); // weekly, monthly, yearly
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('weekly_designs_count');
            $table->integer('monthly_designs_count');
            $table->dateTime('notification_date')->nullable();
            $table->boolean('auto_suspension_enabled')->default(false);
            $table->integer('suspension_period_days')->nullable();
            $table->decimal('total_amount', 15, 2);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('marketing_amount', 15, 2)->nullable();
            $table->boolean('auto_renewal')->default(false);
            $table->text('legal_notes')->nullable();
            $table->timestamps();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
