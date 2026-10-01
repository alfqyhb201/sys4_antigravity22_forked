<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('file')->nullable();
            $table->string('local_path')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_templates');
    }
};
