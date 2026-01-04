<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Formation;
use Illuminate\Database\Eloquent\Factories\Factory;

final class FormationFactory extends Factory
{
    protected $model = Formation::class;

    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'category_id' => null,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraphs(3, true),
            'pricing_tier' => 'free',
            'price' => 0,
            'mode' => 'online',
            'thumbnail' => null,
            'video_trailer' => null,
            'tags' => null,
            'objectives' => null,
            'requirements' => null,
            'target_audience' => null,
            'language' => 'fr',
            'subtitles' => null,
            'difficulty_level' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
            'duration_hours' => fake()->numberBetween(1, 40),
            'duration_minutes' => fake()->numberBetween(0, 59),
            'instructor_name' => fake()->name(),
            'instructor_title' => fake()->jobTitle(),
            'instructor_avatar' => null,
            'instructor_bio' => fake()->paragraph(),
            'meta_title' => fake()->sentence(),
            'meta_description' => fake()->sentence(),
            'meta_keywords' => null,
            'is_published' => fake()->boolean(80),
            'is_featured' => fake()->boolean(20),
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'enrollment_count' => fake()->numberBetween(0, 1000),
            'average_rating' => fake()->randomFloat(1, 1, 5),
            'review_count' => fake()->numberBetween(0, 500),
        ];
    }
}
