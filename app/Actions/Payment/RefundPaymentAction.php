<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Models\Payment;
use App\Support\Stripe\StripePaymentService;
use RuntimeException;

readonly class RefundPaymentAction
{
    public function __construct(
        private StripePaymentService $stripeService
    ) {}

    /**
     * Refund a payment.
     *
     * @param  float|null  $amount  Amount to refund, or null for full refund
     * @return array{payment: Payment, stripe_refund: mixed}
     */
    public function __invoke(Payment $payment, ?float $amount = null, ?string $reason = null): array
    {
        if (! $payment->canBeRefunded()) {
            throw new RuntimeException('Payment cannot be refunded');
        }

        $refundAmount = $amount ?? $payment->refundable_amount;

        // Process refund with Stripe
        $stripeRefund = $this->stripeService->createRefund($payment, $refundAmount, $reason);

        // Update payment status
        $payment->markAsRefunded($refundAmount);

        return [
            'payment' => $payment->fresh()->load(['customer', 'enrollment', 'formation']),
            'stripe_refund' => $stripeRefund,
        ];
    }
}
