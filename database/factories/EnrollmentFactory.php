<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use Illuminate\Database\Eloquent\Factories\Factory;

final class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'formation_id' => Formation::factory(),
            'status' => EnrollmentStatus::ACTIVE,
            'progress_percentage' => fake()->numberBetween(0, 100),
            'enrolled_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'started_at' => fake()->dateTimeBetween('-11 month', 'now'),
            'completed_at' => fake()->optional(0.3, null)->dateTimeBetween('-6 month', 'now'),
            'last_accessed_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'access_count' => fake()->numberBetween(1, 50),
            'amount_paid' => fake()->randomFloat(2, 0, 500),
            'payment_reference' => fake()->optional()->uuid(),
            'metadata' => null,
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::PENDING,
            'started_at' => null,
        ]);
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::ACTIVE,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::COMPLETED,
            'progress_percentage' => 100,
            'completed_at' => fake()->dateTimeBetween('-6 month', 'now'),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::CANCELLED,
            'cancelled_at' => fake()->dateTimeBetween('-3 month', 'now'),
        ]);
    }
}
