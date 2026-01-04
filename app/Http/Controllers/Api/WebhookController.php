<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Payment\ProcessPaymentAction;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\Stripe\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class WebhookController extends Controller
{
    public function __construct(
        private StripePaymentService $stripeService,
        private ProcessPaymentAction $processPaymentAction
    ) {
    }

    /**
     * Handle Stripe webhook events.
     */
    public function handleStripe(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (!$webhookSecret) {
            Log::error('Stripe webhook secret not configured');
            return response()->json(['error' => 'Webhook not configured'], 500);
        }

        // Verify webhook signature
        if (!$this->stripeService->verifyWebhookSignature($payload, $signature, $webhookSecret)) {
            Log::warning('Invalid Stripe webhook signature', [
                'signature' => $signature,
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Construct event
        $event = $this->stripeService->constructWebhookEvent($payload, $signature, $webhookSecret);

        Log::info('Stripe webhook received', [
            'event_type' => $event->type,
            'event_id' => $event->id,
        ]);

        return match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event),
            'payment_intent.amount_capturable_updated' => $this->handlePaymentIntentCapturable($event),
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event),
            'charge.refunded' => $this->handleChargeRefunded($event),
            'charge.refund.updated' => $this->handleChargeRefundUpdated($event),
            default => $this->handleUnknownEvent($event),
        };
    }

    /**
     * Handle payment_intent.succeeded event.
     */
    private function handlePaymentIntentSucceeded(\Stripe\Event $event): JsonResponse
    {
        $paymentIntent = $event->data->object;

        Log::info('Payment intent succeeded', [
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount,
        ]);

        $payment = Payment::byStripePaymentIntent($paymentIntent->id)->first();

        if (!$payment) {
            Log::warning('Payment not found for succeeded intent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // Process payment if not already completed
        if (!$payment->isCompleted()) {
            $paymentMethodType = $paymentIntent->payment_method_types[0] ?? null;
            $this->processPaymentAction->__invoke($paymentIntent->id, $paymentMethodType);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Handle payment_intent.payment_failed event.
     */
    private function handlePaymentIntentFailed(\Stripe\Event $event): JsonResponse
    {
        $paymentIntent = $event->data->object;

        Log::error('Payment intent failed', [
            'payment_intent_id' => $paymentIntent->id,
            'error' => $paymentIntent->last_payment_error?->message ?? 'Unknown error',
        ]);

        $payment = Payment::byStripePaymentIntent($paymentIntent->id)->first();

        if (!$payment) {
            Log::warning('Payment not found for failed intent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // Mark payment as failed
        $payment->markAsFailed(
            $paymentIntent->last_payment_error?->message ?? 'Payment failed',
            $paymentIntent->last_payment_error?->code ?? null
        );

        return response()->json(['received' => true]);
    }

    /**
     * Handle payment_intent.amount_capturable_updated event.
     */
    private function handlePaymentIntentCapturable(\Stripe\Event $event): JsonResponse
    {
        $paymentIntent = $event->data->object;

        Log::info('Payment intent capturable', [
            'payment_intent_id' => $paymentIntent->id,
        ]);

        $payment = Payment::byStripePaymentIntent($paymentIntent->id)->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // Mark payment as processing (ready to capture)
        if ($payment->isPending()) {
            $payment->markAsProcessing();
        }

        return response()->json(['received' => true]);
    }

    /**
     * Handle checkout.session.completed event.
     */
    private function handleCheckoutSessionCompleted(\Stripe\Event $event): JsonResponse
    {
        $session = $event->data->object;

        Log::info('Checkout session completed', [
            'session_id' => $session->id,
            'payment_intent_id' => $session->payment_intent,
        ]);

        $payment = Payment::byStripeCheckoutSession($session->id)->first();

        if (!$payment) {
            Log::warning('Payment not found for completed session', [
                'session_id' => $session->id,
            ]);
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // Update payment with payment intent ID if not already set
        if (!$payment->stripe_payment_intent_id && $session->payment_intent) {
            $payment->update([
                'stripe_payment_intent_id' => $session->payment_intent,
            ]);
        }

        // Note: Actual payment processing happens in payment_intent.succeeded
        // This event just confirms the checkout was completed

        return response()->json(['received' => true]);
    }

    /**
     * Handle charge.refunded event.
     */
    private function handleChargeRefunded(\Stripe\Event $event): JsonResponse
    {
        $charge = $event->data->object;

        Log::info('Charge refunded', [
            'charge_id' => $charge->id,
            'payment_intent_id' => $charge->payment_intent,
            'amount_refunded' => $charge->amount_refunded,
        ]);

        $payment = Payment::byStripePaymentIntent($charge->payment_intent)->first();

        if (!$payment) {
            Log::warning('Payment not found for refunded charge', [
                'payment_intent_id' => $charge->payment_intent,
            ]);
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // Update refunded amount
        $refundAmount = $charge->amount_refunded / 100;
        $payment->markAsRefunded($refundAmount);

        return response()->json(['received' => true]);
    }

    /**
     * Handle charge.refund.updated event.
     */
    private function handleChargeRefundUpdated(\Stripe\Event $event): JsonResponse
    {
        $refund = $event->data->object;

        Log::info('Refund updated', [
            'refund_id' => $refund->id,
            'charge_id' => $refund->charge,
            'status' => $refund->status,
        ]);

        // Refund status can be: pending, succeeded, failed, canceled
        // We already handle the actual refund in charge.refunded event
        // This is just for status tracking

        return response()->json(['received' => true]);
    }

    /**
     * Handle unknown event types.
     */
    private function handleUnknownEvent(\Stripe\Event $event): JsonResponse
    {
        Log::info('Unknown Stripe webhook event', [
            'event_type' => $event->type,
            'event_id' => $event->id,
        ]);

        return response()->json(['received' => true]);
    }
}
