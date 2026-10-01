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
        Schema::create('social_media_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_tag_distribution_id')->constrained('client_tag_distributions')->cascadeOnDelete();
            $table->foreignId('social_media_id')->constrained('social_media')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_posts');
    }
};
