<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SocialMediaPost>
 */
class SocialMediaPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_tag_distribution_id' => \App\Models\ClientTagDistribution::factory(),
            'social_media_id' => \App\Models\SocialMedia::factory(),
            'user_id' => \App\Models\User::factory(),
            'published_at' => now(),
            'notes' => fake()->sentence(),
        ];
    }
}
