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
        Schema::table('clients', function (Blueprint $table) {
            $table->integer('additional_designs_balance')->default(0); // Assuming balance is not a column but calculated, so maybe after 'is_credit_allowed' or similar.
            // $table->integer('additional_designs_balance')->default(0)->after('balance'); // Assuming balance is not a column but calculated, so maybe after 'is_credit_allowed' or similar.
            // Checking Client model, 'is_credit_allowed' exists. 'balance' is an attribute.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('additional_designs_balance');
        });
    }
};
