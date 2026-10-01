<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('created_by_user')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user')
                ->nullable()
                ->after('created_by_user')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['created_by_user']);
            $table->dropForeign(['updated_by_user']);
            $table->dropColumn(['created_by_user', 'updated_by_user']);
        });
    }
};
