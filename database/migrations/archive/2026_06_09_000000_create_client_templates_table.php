<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('file')->nullable();
            $table->string('local_path')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'type']);
        });

        $templateTypes = ['cliche', 'greetings', 'newborns', 'condolences', 'stickers', 'backgrounds'];
        $clientIds = DB::table('clients')->pluck('id');

        foreach ($clientIds as $clientId) {
            foreach ($templateTypes as $type) {
                $file = DB::table('clients')->where('id', $clientId)->value("{$type}_file");
                $localPath = DB::table('clients')->where('id', $clientId)->value("{$type}_local_path");
                $updatedAt = DB::table('clients')->where('id', $clientId)->value("{$type}_updated_at");

                if ($file || $localPath || $updatedAt) {
                    DB::table('client_templates')->insert([
                        'client_id' => $clientId,
                        'type' => $type,
                        'file' => $file,
                        'local_path' => $localPath,
                        'updated_at' => $updatedAt,
                        'created_at' => now(),
                    ]);
                }
            }
        }

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

    public function down(): void
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

        $templates = DB::table('client_templates')->get();

        foreach ($templates as $t) {
            DB::table('clients')->where('id', $t->client_id)->update([
                "{$t->type}_file" => $t->file,
                "{$t->type}_local_path" => $t->local_path,
                "{$t->type}_updated_at" => $t->updated_at,
            ]);
        }

        Schema::dropIfExists('client_templates');
    }
};
