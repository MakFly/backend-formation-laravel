<?php

declare(strict_types=1);

namespace App\Support\Stripe;

use App\Models\Customer;
use App\Models\Formation;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund as StripeRefund;
use Stripe\Stripe;

/**
 * Stripe Payment Service
 *
 * Handles all Stripe API interactions including:
 * - Checkout Session creation
 * - Payment Intents
 * - Refunds
 */
class StripePaymentService
{
    private string $apiKey;

    public function __construct()
    {
        /** @var string $apiKey */
        $apiKey = config('services.stripe.secret_key') ?? 'sk_test_dummy_key';
        $this->apiKey = $apiKey;
        Stripe::setApiKey($this->apiKey);
    }

    /**
     * Create a Stripe Checkout Session.
     */
    public function createCheckoutSession(Payment $payment, Customer $customer, Formation $formation): string
    {
        try {
            $session = Session::create([
                'payment_intent_data' => [
                    'metadata' => [
                        'payment_id' => $payment->id,
                        'customer_id' => $customer->id,
                        'formation_id' => $formation->id,
                    ],
                ],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $formation->title,
                            'description' => substr($formation->summary ?: $formation->title, 0, 500),
                        ],
                        'unit_amount' => (int) round($payment->amount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => config('app.url').'/api/v1/payments/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.url').'/api/v1/payments/cancel?session_id={CHECKOUT_SESSION_ID}',
                'customer_email' => $customer->email,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'customer_id' => $customer->id,
                    'formation_id' => $formation->id,
                ],
            ]);

            // Update payment with session ID
            $payment->update([
                'stripe_checkout_session_id' => $session->id,
                'stripe_payment_intent_id' => $session->payment_intent,
            ]);

            return $session->url;
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout session creation failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);
            throw $e;
        }
    }

    /**
     * Retrieve a Payment Intent from Stripe.
     */
    public function getPaymentIntent(string $paymentIntentId): \Stripe\PaymentIntent
    {
        try {
            return \Stripe\PaymentIntent::retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment intent retrieval failed', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);
            throw $e;
        }
    }

    /**
     * Create a refund.
     */
    public function createRefund(Payment $payment, float $amount, ?string $reason = null): StripeRefund
    {
        try {
            $refundData = [
                'payment_intent' => $payment->stripe_payment_intent_id,
                'amount' => (int) round($amount * 100),
                'metadata' => [
                    'payment_id' => $payment->id,
                ],
            ];

            if ($reason !== null) {
                $refundData['reason'] = $reason;
            }

            return StripeRefund::create($refundData);
        } catch (ApiErrorException $e) {
            Log::error('Stripe refund creation failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'amount' => $amount,
            ]);
            throw $e;
        }
    }

    /**
     * Retrieve a Checkout Session from Stripe.
     */
    public function getCheckoutSession(string $sessionId): Session
    {
        try {
            return Session::retrieve($sessionId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout session retrieval failed', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
            ]);
            throw $e;
        }
    }

    /**
     * Verify Stripe webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $webhookSecret): bool
    {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $webhookSecret
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Construct a webhook event from payload.
     */
    public function constructWebhookEvent(string $payload, string $signature, string $webhookSecret): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            $webhookSecret
        );
    }
}
