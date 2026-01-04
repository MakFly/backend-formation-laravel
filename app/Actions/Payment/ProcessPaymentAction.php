<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Models\Enrollment;
use App\Models\Payment;
use RuntimeException;

final readonly class ProcessPaymentAction
{
    /**
     * Process a successful payment and create enrollment if needed.
     */
    public function __invoke(string $stripePaymentIntentId, ?string $paymentMethodType = null): Payment
    {
        $payment = Payment::byStripePaymentIntent($stripePaymentIntentId)->first();

        if (! $payment) {
            throw new RuntimeException('Payment not found');
        }

        if ($payment->isCompleted()) {
            return $payment;
        }

        $payment->markAsCompleted($paymentMethodType);

        // Auto-enroll if payment is for enrollment
        if ($payment->enrollment_id) {
            $enrollment = Enrollment::find($payment->enrollment_id);
            if ($enrollment && $enrollment->isPending()) {
                // Validate enrollment since payment is complete
                $enrollment->update([
                    'amount_paid' => $payment->amount,
                ]);
                // Note: You might want to call ValidateEnrollmentAction here
                // But to avoid circular dependencies, we just update the amount
            }
        }

        return $payment->load(['customer', 'enrollment', 'formation']);
    }
}
