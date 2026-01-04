<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'enrollment_id' => null,
            'formation_id' => null,
            'type' => PaymentType::ENROLLMENT,
            'status' => PaymentStatus::PENDING,
            'stripe_payment_intent_id' => 'pi_'.$this->faker->unique()->regexify('[a-zA-Z0-9]{24}'),
            'stripe_checkout_session_id' => 'cs_'.$this->faker->unique()->regexify('[a-zA-Z0-9]{24}'),
            'stripe_invoice_id' => null,
            'stripe_subscription_id' => null,
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'amount_refunded' => 0,
            'currency' => 'EUR',
            'payment_method_type' => null,
            'description' => $this->faker->sentence(),
            'failure_reason' => null,
            'failure_code' => null,
            'metadata' => null,
            'stripe_response' => null,
            'paid_at' => null,
            'refunded_at' => null,
            'failed_at' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::COMPLETED,
            'payment_method_type' => $this->faker->randomElement(['card', 'sepa_debit', 'sofort']),
            'paid_at' => now()->subDay(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::FAILED,
            'failure_reason' => 'Card declined',
            'failure_code' => 'card_declined',
            'failed_at' => now()->subDay(),
        ]);
    }

    public function refunded(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::REFUNDED,
            'amount_refunded' => $attributes['amount'],
            'refunded_at' => now()->subHour(),
        ]);
    }

    public function partiallyRefunded(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::PARTIALLY_REFUNDED,
            'amount_refunded' => $attributes['amount'] * 0.5,
            'refunded_at' => now()->subHour(),
        ]);
    }

    public function withAmount(float $amount): self
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }
}
