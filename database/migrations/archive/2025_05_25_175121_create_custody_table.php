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
        Schema::create('custody', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // يربط بالمستخدم المستلم
            $table->timestamps(); // created_at + updated_at
            $table->foreignId('added_by_user')->nullable()->constrained('users')->onDelete('set null'); // من أضاف
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->onDelete('set null'); // من عدّل آخر مرة
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custody');
    }
};
