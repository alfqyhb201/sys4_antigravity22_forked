<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('simple_requests_enabled')->default(false)->after('additional_design_price');
            $table->decimal('simple_request_price', 10, 2)->nullable()->after('simple_requests_enabled');
            $table->integer('simple_requests_count')->default(0)->after('simple_request_price');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('simple_requests_enabled');
            $table->dropColumn('simple_request_price');
            $table->dropColumn('simple_requests_count');
        });
    }
};
