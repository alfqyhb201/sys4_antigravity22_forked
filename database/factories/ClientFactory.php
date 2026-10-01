<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company' => fake()->company(),
            'status' => true,
            'enable_very_high' => true,
            'cliche_counter' => 0,
            'added_by_user' => User::factory(),
        ];
    }

    public function withWeights(array $weights): static
    {
        return $this->state(fn () => [
            'importance_weights' => $weights,
        ]);
    }

    public function veryHighDisabled(): static
    {
        return $this->state(fn () => [
            'enable_very_high' => false,
        ]);
    }
}
