<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Payment;
use App\Support\Stripe\StripePaymentService;

final readonly class CreatePaymentAction
{
    public function __construct(
        private StripePaymentService $stripeService
    ) {}

    /**
     * Create a payment for enrollment.
     *
     * @return array{payment: Payment, checkout_url: string}
     */
    public function forEnrollment(Customer $customer, Formation $formation, ?Enrollment $enrollment = null): array
    {
        $payment = Payment::create([
            'customer_id' => $customer->id,
            'enrollment_id' => $enrollment?->id,
            'formation_id' => $formation->id,
            'type' => PaymentType::ENROLLMENT->value,
            'status' => PaymentStatus::PENDING->value,
            'amount' => $formation->price,
            'currency' => 'EUR',
            'description' => "Enrollment: {$formation->title}",
        ]);

        // Create Stripe Checkout Session
        $checkoutUrl = $this->stripeService->createCheckoutSession($payment, $customer, $formation);

        return [
            'payment' => $payment->load(['customer', 'formation']),
            'checkout_url' => $checkoutUrl,
        ];
    }

    /**
     * Create a payment by direct amount.
     */
    public function direct(array $data): Payment
    {
        return Payment::create([
            'customer_id' => $data['customer_id'],
            'enrollment_id' => $data['enrollment_id'] ?? null,
            'formation_id' => $data['formation_id'] ?? null,
            'type' => PaymentType::from($data['type'])->value,
            'status' => PaymentStatus::PENDING->value,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'EUR',
            'description' => $data['description'] ?? null,
        ]);
    }
}
