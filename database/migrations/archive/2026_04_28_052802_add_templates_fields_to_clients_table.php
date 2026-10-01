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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('cliche_file')->nullable();
            $table->string('cliche_local_path')->nullable();
            $table->timestamp('cliche_updated_at')->nullable();

            $table->string('greetings_file')->nullable();
            $table->string('greetings_local_path')->nullable();
            $table->timestamp('greetings_updated_at')->nullable();

            $table->string('newborns_file')->nullable();
            $table->string('newborns_local_path')->nullable();
            $table->timestamp('newborns_updated_at')->nullable();

            $table->string('condolences_file')->nullable();
            $table->string('condolences_local_path')->nullable();
            $table->timestamp('condolences_updated_at')->nullable();

            $table->string('stickers_file')->nullable();
            $table->string('stickers_local_path')->nullable();
            $table->timestamp('stickers_updated_at')->nullable();

            $table->string('backgrounds_file')->nullable();
            $table->string('backgrounds_local_path')->nullable();
            $table->timestamp('backgrounds_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'cliche_file', 'cliche_local_path', 'cliche_updated_at',
                'greetings_file', 'greetings_local_path', 'greetings_updated_at',
                'newborns_file', 'newborns_local_path', 'newborns_updated_at',
                'condolences_file', 'condolences_local_path', 'condolences_updated_at',
                'stickers_file', 'stickers_local_path', 'stickers_updated_at',
                'backgrounds_file', 'backgrounds_local_path', 'backgrounds_updated_at',
            ]);
        });
    }
};
