<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'complaints',
        'ideas',
        'locations',
        'social_media',
        'tags',
        'tags_groups',
        'currencies',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            DB::table($table)
                ->whereNull('created_by_user')
                ->whereNotNull('added_by_user')
                ->update(['created_by_user' => DB::raw('added_by_user')]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::table($table)
                ->whereNotNull('added_by_user')
                ->update(['created_by_user' => null]);
        }
    }
};
