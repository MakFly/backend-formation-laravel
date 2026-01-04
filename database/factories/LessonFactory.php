<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Formation;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

final class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'module_id' => Module::factory(),
            'formation_id' => Formation::factory(),
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'summary' => fake()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'video_url' => fake()->optional()->url(),
            'thumbnail' => null,
            'duration_seconds' => fake()->numberBetween(300, 7200),
            'is_preview' => fake()->boolean(10),
            'is_published' => true,
            'order' => fake()->numberBetween(0, 20),
        ];
    }
}
