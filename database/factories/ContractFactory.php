<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contract>
 */
class ContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => \App\Models\Client::factory(),
            'currency_id' => \App\Models\Currency::factory(),
            'payment_type' => 'monthly',
            'billing_cycle' => 'monthly',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'weekly_designs_count' => 0,
            'monthly_designs_count' => 0,
            'total_amount' => 1000,
        ];
    }
}
