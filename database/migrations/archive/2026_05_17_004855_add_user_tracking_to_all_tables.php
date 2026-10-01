<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'categories',
        'currencies',
        'custody',
        'designers',
        'design_tasks',
        'client_needs',
        'complaints',
        'ideas',
        'locations',
        'social_media',
        'tags_groups',
        'tags',
        'transactions',
        'client_tag_distributions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'created_by_user')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('created_by_user')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                });
            }

            if (! Schema::hasColumn($tableName, 'updated_by_user')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('updated_by_user')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['created_by_user']);
                $table->dropForeign(['updated_by_user']);
                $table->dropColumn(['created_by_user', 'updated_by_user']);
            });
        }
    }
};
