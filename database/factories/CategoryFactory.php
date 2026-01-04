<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'parent_id' => null,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'description' => fake()->paragraph(),
            'icon' => null,
            'color' => fake()->hexColor(),
            'image' => null,
            'order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
