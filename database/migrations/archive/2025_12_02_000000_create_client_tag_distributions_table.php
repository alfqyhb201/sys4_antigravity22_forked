<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_tag_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_designer_id')->constrained('client_designer')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->date('distribution_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tag_distributions');
    }
};
