<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LessonResource;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

final class LessonResourceFactory extends Factory
{
    protected $model = LessonResource::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'title' => fake()->words(3, true),
            'type' => fake()->randomElement(['pdf', 'video', 'audio', 'image', 'document', 'archive', 'link', 'code', 'attachment']),
            'file_path' => fake()->optional()->filePath(),
            'file_url' => fake()->optional()->url(),
            'file_name' => fake()->optional()->word() . '.' . fake()->fileExtension(),
            'mime_type' => fake()->optional()->mimeType(),
            'file_size' => fake()->optional()->numberBetween(1024, 10485760),
            'duration' => fake()->optional()->numberBetween(60, 3600),
            'description' => fake()->optional()->paragraph(),
            'is_downloadable' => true,
            'order' => fake()->numberBetween(0, 10),
        ];
    }
}
