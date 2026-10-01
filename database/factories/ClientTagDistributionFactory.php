<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClientTagDistribution>
 */
class ClientTagDistributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_designer_id' => \App\Models\ClientDesigner::factory(),
            'tag_id' => \App\Models\Tag::factory(),
            'distribution_date' => now()->toDateString(),
            'scheduled_sending_at' => now(),
            'status' => 'sending',
            'attachment_path' => 'designs/sample.png',
        ];
    }
}
