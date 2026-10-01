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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps(); // يحتوي على created_at و updated_at

            $table->unsignedBigInteger('added_by_user')->nullable();
            $table->unsignedBigInteger('updated_by_user')->nullable();

            // مفاتيح خارجية تربط المستخدمين (اختياري)
            $table->foreign('added_by_user')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by_user')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
