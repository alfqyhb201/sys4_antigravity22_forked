<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().'_group',
            'added_by_user' => User::factory(),
        ];
    }
}
