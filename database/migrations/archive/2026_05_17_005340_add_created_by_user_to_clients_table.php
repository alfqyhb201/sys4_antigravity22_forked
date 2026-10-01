<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'created_by_user')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->foreignId('created_by_user')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        DB::table('clients')
            ->whereNull('created_by_user')
            ->whereNotNull('added_by_user')
            ->update(['created_by_user' => DB::raw('added_by_user')]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['created_by_user']);
            $table->dropColumn('created_by_user');
        });
    }
};
