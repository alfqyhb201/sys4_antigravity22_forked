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
        if (! Schema::hasColumn('receipt_allocations', 'updated_by_user')) {
            Schema::table('receipt_allocations', function (Blueprint $table) {
                $table->foreignId('updated_by_user')->nullable()->after('created_by_user')->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('receipt_allocations', 'updated_by_user')) {
            Schema::table('receipt_allocations', function (Blueprint $table) {
                $table->dropForeign(['updated_by_user']);
                $table->dropColumn('updated_by_user');
            });
        }
    }
};
