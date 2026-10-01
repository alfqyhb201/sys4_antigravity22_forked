<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignId('paid_currency_id')->nullable()->after('client_id')->constrained('currencies')->nullOnDelete();
            $table->decimal('original_amount', 15, 2)->nullable()->after('amount');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000)->after('original_amount');
            $table->decimal('base_currency_amount', 15, 2)->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['paid_currency_id']);
            $table->dropColumn(['paid_currency_id', 'original_amount', 'exchange_rate', 'base_currency_amount']);
        });
    }
};
