<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

final class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'type' => $this->faker->randomElement(['individual', 'company']),
            'company_name' => $this->faker->optional()->company(),
            'company_siret' => $this->faker->optional()->numerify('###########'),
            'company_tva_number' => $this->faker->optional()->regexify('[A-Z]{2}[0-9]{11}'),
            'metadata' => null,
        ];
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'individual',
            'company_name' => null,
            'company_siret' => null,
            'company_tva_number' => null,
        ]);
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'company',
            'company_name' => $this->faker->company(),
            'company_siret' => $this->faker->numerify('###########'),
            'company_tva_number' => sprintf('FR%s', $this->faker->numerify('#########')),
        ]);
    }
}
