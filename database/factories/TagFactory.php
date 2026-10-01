<?php

namespace Database\Factories;

use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().'_tag',
            'importance' => 'medium',
            'tag_group_id' => TagGroup::factory(),
            'is_active' => true,
            'is_auto_assigned' => true,
            'is_there_date_for_sending' => false,
            'added_by_user' => User::factory(),
        ];
    }

    public function veryHigh(): static
    {
        return $this->state(fn () => ['importance' => 'veryhigh']);
    }

    public function high(): static
    {
        return $this->state(fn () => ['importance' => 'high']);
    }

    public function medium(): static
    {
        return $this->state(fn () => ['importance' => 'medium']);
    }

    public function low(): static
    {
        return $this->state(fn () => ['importance' => 'low']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function autoAssigned(): static
    {
        return $this->state(fn () => ['is_auto_assigned' => true]);
    }
}
