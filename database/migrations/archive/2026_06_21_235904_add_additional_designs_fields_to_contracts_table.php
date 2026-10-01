<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('additional_designs_enabled')->default(false)->after('monthly_designs_count');
            $table->decimal('additional_design_price', 10, 2)->nullable()->after('additional_designs_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('additional_designs_enabled');
            $table->dropColumn('additional_design_price');
        });
    }
};
