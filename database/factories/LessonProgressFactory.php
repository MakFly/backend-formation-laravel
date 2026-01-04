<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

final class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'lesson_id' => Lesson::factory(),
            'status' => fake()->randomElement(LessonProgressStatus::class),
            'progress_percentage' => fake()->numberBetween(0, 100),
            'started_at' => fake()->dateTimeBetween('-6 month', 'now'),
            'completed_at' => fake()->optional(0.4, null)->dateTimeBetween('-3 month', 'now'),
            'last_accessed_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'time_spent_seconds' => fake()->numberBetween(0, 7200),
            'access_count' => fake()->numberBetween(1, 20),
            'current_position' => fake()->numberBetween(0, 3600),
            'is_favorite' => fake()->boolean(20),
            'metadata' => null,
            'notes' => null,
        ];
    }

    public function notStarted(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => LessonProgressStatus::NOT_STARTED,
            'progress_percentage' => 0,
            'started_at' => null,
        ]);
    }

    public function inProgress(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => LessonProgressStatus::IN_PROGRESS,
            'progress_percentage' => fake()->numberBetween(1, 99),
            'started_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => LessonProgressStatus::COMPLETED,
            'progress_percentage' => 100,
            'completed_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function favorite(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_favorite' => true,
        ]);
    }
}
