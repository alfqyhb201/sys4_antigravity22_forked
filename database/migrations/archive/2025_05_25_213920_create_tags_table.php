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
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('importance', ['veryhigh', 'high', 'medium', 'low'])->default('medium');

            $table->foreignId('tag_group_id')->constrained('tags_groups')->cascadeOnDelete();

            $table->boolean('is_repetition')->default(false);
            $table->enum('repetition', ['weekly', 'monthly', 'yearly'])->nullable();
            $table->integer('weekly_times')->nullable();
            $table->integer('monthly_times')->nullable();
            $table->integer('yearly_times')->nullable();

            $table->boolean('is_there_date_for_sending')->default(false);
            $table->date('date_for_sending_yearly')->nullable();
            $table->enum('weekly_day', ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])->nullable();
            $table->time('weekly_time')->nullable();
            $table->time('weekly_time_sm')->nullable();
            $table->timestamps();
            $table->foreignId('added_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
