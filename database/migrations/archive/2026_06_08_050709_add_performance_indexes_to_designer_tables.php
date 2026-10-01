<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designers', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('client_designer', function (Blueprint $table) {
            $table->index(['designer_id', 'week_start_date']);
        });

        Schema::table('client_tag_distributions', function (Blueprint $table) {
            $table->index('client_designer_id');
            $table->index('status');
            $table->index(['client_designer_id', 'status']);
        });

        Schema::table('design_tasks', function (Blueprint $table) {
            $table->index('designer_id');
            $table->index(['designer_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['designer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['designer_id', 'status']);
        });

        Schema::table('design_tasks', function (Blueprint $table) {
            $table->dropIndex(['designer_id', 'status']);
            $table->dropIndex(['designer_id']);
        });

        Schema::table('client_tag_distributions', function (Blueprint $table) {
            $table->dropIndex(['client_designer_id', 'status']);
            $table->dropIndex(['status']);
            $table->dropIndex(['client_designer_id']);
        });

        Schema::table('client_designer', function (Blueprint $table) {
            $table->dropIndex(['designer_id', 'week_start_date']);
        });

        Schema::table('designers', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
