<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * مصنع بيانات تجريبية لنموذج المصمم.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Designer>
 */
class DesignerFactory extends Factory
{
    /**
     * تعريف الحالة الافتراضية للنموذج.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'min_capacity' => fake()->numberBetween(5, 10),
            'max_capacity' => fake()->numberBetween(15, 30),
            'rate' => fake()->randomFloat(1, 3, 5),
            'shift_hours' => fake()->randomElement([6, 8]),
        ];
    }
}
