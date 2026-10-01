<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designer_id')->constrained('designers')->cascadeOnDelete()->index();
            $table->foreignId('assigner_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_subscribed_client')->default(false);
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->text('description')->nullable();
            $table->json('reference_files')->nullable();
            $table->boolean('is_extra')->default(false);
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('priority')->default('medium');
            $table->boolean('deduct_from_balance')->default(false);
            $table->string('status')->default('pending');
            $table->json('design_files')->nullable();
            $table->text('revision_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_template_update')->default(false);
            $table->string('template_type')->nullable();
            $table->string('local_path')->nullable();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('additional_designs_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->softDeletes();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['designer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_tasks');
    }
};
