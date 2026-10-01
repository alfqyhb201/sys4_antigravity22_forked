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
        Schema::create('client_need_tags_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_need_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tag_id')->nullable();
            $table->foreign('tag_id')
                ->references('id')->on('tags')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_need_tags_group');
    }
};
