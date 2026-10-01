<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'invoice_number' => 'INV-'.fake()->unique()->numberBetween(1000, 9999),
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'total_amount' => 0.00,
            'status' => 'draft',
            'notes' => fake()->sentence(),
            'created_by_user' => User::factory(),
        ];
    }
}
