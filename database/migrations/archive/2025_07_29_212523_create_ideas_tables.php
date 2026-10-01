<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ideas
        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('content')->nullable();
            $table->text('description')->nullable();
            $table->boolean('repeat_for_clients')->default(false);
            $table->dateTime('scheduled_at')->nullable();
            $table->text('idea_file')->nullable();
            $table->boolean('is_visible_in_generator')->default(true);
            $table->timestamps();
            $table->foreignId('added_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
        });

        // client_idea
        Schema::create('client_idea', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->timestamps();
            $table->foreignId('added_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
        });

        // idea_client_blocks
        Schema::create('idea_client_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->timestamps();
            $table->foreignId('added_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
        });

        // location_idea
        Schema::create('location_idea', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();
            $table->foreignId('added_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
        });

        // tag_idea
        Schema::create('tag_idea', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamps();
            $table->foreignId('added_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_idea');
        Schema::dropIfExists('location_idea');
        Schema::dropIfExists('idea_client_blocks');
        Schema::dropIfExists('client_idea');
        Schema::dropIfExists('ideas');
    }
};
