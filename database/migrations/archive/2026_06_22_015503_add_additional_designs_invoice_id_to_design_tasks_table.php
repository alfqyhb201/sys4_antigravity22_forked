<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->foreignId('additional_designs_invoice_id')
                ->nullable()
                ->after('contract_id')
                ->constrained('invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('additional_designs_invoice_id');
        });
    }
};
