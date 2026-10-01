<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'عبدالله الفقيه',
            'username' => 'admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('123321'),
            'work_phone_number' => '777777777',
            'personal_phone_number' => '777777777',
            'status' => 1,
            'hire_date' => '2023-12-28',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
