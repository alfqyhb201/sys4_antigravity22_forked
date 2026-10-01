<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->boolean('is_base')->default(false)->after('value');
            $table->string('symbol', 10)->nullable()->after('is_base');
            $table->unsignedTinyInteger('decimal_places')->default(2)->after('symbol');
            $table->boolean('is_active')->default(true)->after('decimal_places');
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['is_base', 'symbol', 'decimal_places', 'is_active']);
        });
    }
};
