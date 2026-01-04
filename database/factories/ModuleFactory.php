<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Formation;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        $title = fake()->words(2, true);

        return [
            'formation_id' => Formation::factory(),
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(['video', 'text', 'interactive', 'quiz', 'assignment', 'mixed']),
            'order' => fake()->numberBetween(0, 10),
            'is_published' => true,
            'is_free' => fake()->boolean(20),
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
