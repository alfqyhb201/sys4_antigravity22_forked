<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency' => fake()->unique()->currencyCode(),
            'currency_name' => fake()->unique()->currencyCode().' - '.fake()->word(),
            'value' => fake()->randomFloat(2, 0.5, 5),
            'added_by_user' => \App\Models\User::factory(),
        ];
    }
}
