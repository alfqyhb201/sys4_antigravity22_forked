<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Designer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_name' => fake()->company(),
            'description' => fake()->optional()->sentence(),
            'designer_id' => Designer::factory(),
            'assigner_id' => User::factory(),
            'status' => OrderStatus::Pending,
        ];
    }
}
