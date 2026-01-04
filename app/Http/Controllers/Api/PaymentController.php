<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Payment\CreatePaymentAction;
use App\Actions\Payment\RefundPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\RefundPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Support\Http\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function __construct(
        private CreatePaymentAction $createPaymentAction,
        private RefundPaymentAction $refundPaymentAction
    ) {}

    /**
     * Create a payment for a formation enrollment.
     */
    public function store(CreatePaymentRequest $request): JsonResponse
    {
        $customer = Customer::findOrFail($request->customer_id);
        $formation = \App\Models\Formation::findOrFail($request->formation_id);

        $enrollment = null;
        if ($request->enrollment_id) {
            $enrollment = \App\Models\Enrollment::findOrFail($request->enrollment_id);
        }

        $result = $this->createPaymentAction->forEnrollment($customer, $formation, $enrollment);

        return ApiResponseBuilder::created([
            'payment' => PaymentResource::make($result['payment']),
            'checkout_url' => $result['checkout_url'],
        ], 'Payment created successfully');
    }

    /**
     * Get payment details.
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $payment = Payment::with(['customer', 'enrollment', 'formation'])->findOrFail($id);

        // Ensure customer can only view their own payments
        if ($request->user()?->customer_id !== $payment->customer_id) {
            return ApiResponseBuilder::forbidden('You do not have permission to view this payment');
        }

        return ApiResponseBuilder::success(PaymentResource::make($payment));
    }

    /**
     * List payments for a customer.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['customer', 'enrollment', 'formation']);

        // Filter by customer if specified
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by formation if specified
        if ($request->has('formation_id')) {
            $query->where('formation_id', $request->formation_id);
        }

        // Filter by status if specified
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type if specified
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $perPage = (int) $request->input('per_page', 30);
        $page = (int) $request->input('page', 1);

        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return ApiResponseBuilder::success([
            'data' => PaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    /**
     * Process a refund for a payment.
     */
    public function refund(string $id, RefundPaymentRequest $request): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        // Ensure customer can only refund their own payments
        if ($request->user()?->customer_id !== $payment->customer_id) {
            return ApiResponseBuilder::forbidden('You do not have permission to refund this payment');
        }

        $result = $this->refundPaymentAction->__invoke(
            $payment,
            $request->amount,
            $request->reason
        );

        return ApiResponseBuilder::success([
            'payment' => PaymentResource::make($result['payment']),
            'stripe_refund' => [
                'id' => $result['stripe_refund']->id,
                'amount' => $result['stripe_refund']->amount,
                'currency' => $result['stripe_refund']->currency,
                'status' => $result['stripe_refund']->status,
                'created' => $result['stripe_refund']->created,
            ],
        ], 'Refund processed successfully');
    }

    /**
     * Handle successful payment redirect from Stripe Checkout.
     */
    public function success(Request $request): JsonResponse
    {
        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            return ApiResponseBuilder::error('Missing session ID', 'MISSING_SESSION_ID');
        }

        $payment = Payment::byStripeCheckoutSession($sessionId)->first();

        if (! $payment) {
            return ApiResponseBuilder::notFound('Payment not found');
        }

        return ApiResponseBuilder::success(PaymentResource::make($payment->load(['customer', 'enrollment', 'formation'])));
    }

    /**
     * Handle cancelled payment redirect from Stripe Checkout.
     */
    public function cancel(Request $request): JsonResponse
    {
        $sessionId = $request->query('session_id');

        if ($sessionId) {
            $payment = Payment::byStripeCheckoutSession($sessionId)->first();

            if ($payment) {
                return ApiResponseBuilder::success([
                    'payment' => PaymentResource::make($payment),
                    'message' => 'Payment was cancelled',
                ]);
            }
        }

        return ApiResponseBuilder::success([
            'message' => 'Payment was cancelled',
        ]);
    }
}
