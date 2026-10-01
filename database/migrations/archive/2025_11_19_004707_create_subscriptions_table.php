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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->boolean('is_main');
            $table->integer('designs_count');
            $table->date('start_date');
            $table->enum('subscription_type', ['weekly', 'monthly', 'yearly']);
            $table->date('next_renewal_date')->nullable();
            $table->decimal('payment_amount', 10, 2);
            $table->foreignId('currency_id')->constrained()->onDelete('restrict');
            $table->enum('payment_type', ['advance', 'deferred']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
