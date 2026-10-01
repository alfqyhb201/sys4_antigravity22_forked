<?php

namespace Database\Factories;

use App\Filament\Enums\DesignTaskPriority;
use App\Filament\Enums\DesignTaskStatus;
use App\Models\Designer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * مصنع بيانات تجريبية لنموذج مهمة التصميم.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DesignTask>
 */
class DesignTaskFactory extends Factory
{
    /**
     * تعريف الحالة الافتراضية للنموذج.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'designer_id' => Designer::factory(),
            'assigner_id' => User::factory(),
            'is_subscribed_client' => false,
            'client_id' => null,
            'client_name' => fake()->company(),
            'description' => fake()->paragraph(),
            'reference_files' => null,
            'is_extra' => false,
            'amount' => null,
            'priority' => fake()->randomElement(DesignTaskPriority::cases()),
            'status' => DesignTaskStatus::Pending,
        ];
    }

    /**
     * حالة المهمة قيد المراجعة.
     */
    public function inReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DesignTaskStatus::InReview,
            'submitted_at' => now(),
        ]);
    }

    /**
     * حالة المهمة تحتاج تعديل.
     */
    public function needsRevision(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DesignTaskStatus::NeedsRevision,
            'revision_notes' => fake()->sentence(),
        ]);
    }

    /**
     * حالة المهمة معتمدة.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DesignTaskStatus::Approved,
            'submitted_at' => now(),
        ]);
    }

    /**
     * حالة المهمة بأهمية عالية.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => DesignTaskPriority::High,
        ]);
    }
}
