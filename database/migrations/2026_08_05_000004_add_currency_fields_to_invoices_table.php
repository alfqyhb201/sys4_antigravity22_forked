<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('contract_id')->constrained('currencies')->nullOnDelete();
            $table->decimal('exchange_rate', 18, 6)->default(1.000000)->after('total_amount');
            $table->decimal('base_currency_amount', 15, 2)->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['currency_id', 'exchange_rate', 'base_currency_amount']);
        });
    }
};
