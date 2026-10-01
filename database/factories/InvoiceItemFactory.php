<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->words(3, true),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_amount' => fake()->randomFloat(2, 50, 500),
            'total' => fn (array $attributes) => $attributes['quantity'] * $attributes['unit_amount'],
        ];
    }
}
