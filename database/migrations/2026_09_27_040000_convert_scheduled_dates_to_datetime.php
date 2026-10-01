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
        Schema::table('client_tag_distributions', function (Blueprint $table) {
            $table->dateTime('scheduled_sending_at')->nullable()->change();
            $table->dateTime('completed_at')->nullable()->change();
        });

        if (Schema::hasTable('design_tasks') && Schema::hasColumn('design_tasks', 'scheduled_at')) {
            Schema::table('design_tasks', function (Blueprint $table) {
                $table->dateTime('scheduled_at')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_tag_distributions', function (Blueprint $table) {
            $table->timestamp('scheduled_sending_at')->nullable()->change();
            $table->timestamp('completed_at')->nullable()->change();
        });

        if (Schema::hasTable('design_tasks') && Schema::hasColumn('design_tasks', 'scheduled_at')) {
            Schema::table('design_tasks', function (Blueprint $table) {
                $table->timestamp('scheduled_at')->nullable()->change();
            });
        }
    }
};
