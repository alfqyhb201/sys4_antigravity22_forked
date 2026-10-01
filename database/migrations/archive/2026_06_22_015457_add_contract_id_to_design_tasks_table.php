<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->foreignId('contract_id')
                ->nullable()
                ->after('client_id')
                ->constrained('contracts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_id');
        });
    }
};
