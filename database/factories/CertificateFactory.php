<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CertificateStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class CertificateFactory extends Factory
{
    public function definition(): array
    {
        $certificateNumber = 'CERT-'.strtoupper(Str::random(12));
        $verificationCode = strtoupper(Str::random(8));

        return [
            'enrollment_id' => null, // Set by tests
            'customer_id' => null, // Set by tests
            'formation_id' => null, // Set by tests
            'certificate_number' => $certificateNumber,
            'status' => CertificateStatus::ACTIVE->value,
            'issued_at' => now(),
            'expires_at' => null,
            'revoked_at' => null,
            'revoked_reason' => null,
            'verification_code' => $verificationCode,
            'student_name' => $this->faker->name(),
            'formation_title' => $this->faker->sentence(3),
            'instructor_name' => $this->faker->name(),
            'completion_date' => $this->faker->date(),
            'pdf_path' => null,
            'pdf_size_bytes' => null,
            'metadata' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificateStatus::ACTIVE->value,
            'issued_at' => now(),
            'revoked_at' => null,
            'revoked_reason' => null,
        ]);
    }

    public function revoked(string $reason = 'Revoked by admin'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificateStatus::REVOKED->value,
            'revoked_at' => now(),
            'revoked_reason' => $reason,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificateStatus::EXPIRED->value,
            'expires_at' => now()->subYear(),
        ]);
    }

    public function withExpiry(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays($days),
        ]);
    }
}
