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
        Schema::create('designers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('min_capacity')->nullable();
            $table->integer('max_capacity')->nullable();
            $table->integer('weekly_capacity')->nullable();
            $table->integer('rating')->nullable();
            $table->decimal('rate', 8, 2)->nullable();
            $table->integer('shift_hours')->nullable();
            $table->decimal('discipline_score', 5, 2)->nullable();
            $table->integer('amount_of_designs')->nullable();
            $table->string('freepik_account')->nullable();
            $table->string('pc_number')->nullable();
            $table->timestamps();
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
        Schema::dropIfExists('designers');
    }
};
