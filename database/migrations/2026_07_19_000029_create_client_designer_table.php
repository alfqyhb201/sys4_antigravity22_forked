<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_designer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('designer_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date')->nullable()->index();
            $table->foreignId('contract_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Performance indexes
            $table->index(['designer_id', 'week_start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_designer');
    }
};
