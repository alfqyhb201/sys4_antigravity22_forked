<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('to_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('rate', 18, 6);
            $table->date('effective_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['from_currency_id', 'to_currency_id', 'effective_date'], 'from_to_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
