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
        if (! Schema::hasColumn('invoices', 'deleted_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('receipts', 'deleted_at')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('contracts', 'deleted_at')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('clients', 'deleted_at')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
