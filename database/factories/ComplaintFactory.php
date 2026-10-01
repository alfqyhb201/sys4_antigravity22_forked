<?php

namespace Database\Factories;

use App\Filament\Enums\ComplaintStatus;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * مصنع بيانات تجريبية لنموذج الشكوى.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Complaint>
 */
class ComplaintFactory extends Factory
{
    /**
     * تعريف الحالة الافتراضية للنموذج.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(ComplaintStatus::cases()),
            'added_by_user' => User::factory(),
            'updated_by_user' => null,
        ];
    }

    /**
     * حالة الشكوى الجديدة.
     */
    public function new($attributes = []): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplaintStatus::New,
        ]);
    }

    /**
     * حالة الشكوى المحلولة.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplaintStatus::Resolved,
        ]);
    }
}
