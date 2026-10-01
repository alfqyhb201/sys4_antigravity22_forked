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
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->date('distribution_date');
            $table->foreignId('idea_id')->nullable()->constrained()->nullOnDelete();
            $table->text('custom_idea')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('designer_notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('reviewer_feedback')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_sending_at')->nullable();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_designer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tag_distributions');
    }
};
