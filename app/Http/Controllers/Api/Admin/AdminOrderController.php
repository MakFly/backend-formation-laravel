<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Payment\RefundPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Support\Http\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminOrderController extends Controller
{
    public function __construct(
        private RefundPaymentAction $refundAction
    ) {}

    /**
     * List orders (payments) with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['customer', 'formation', 'enrollment']);

        // Search filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('stripe_payment_intent_id', 'like', "%{$search}%")
                    ->orWhere('stripe_checkout_session_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('email', 'like', "%{$search}%"))
                    ->orWhereHas('formation', fn ($q) => $q->where('title', 'like', "%{$search}%"));
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        // Filter by formation
        if ($request->has('formation_id')) {
            $query->where('formation_id', $request->input('formation_id'));
        }

        // Filter by payment method
        if ($request->has('payment_method_type')) {
            $query->where('payment_method_type', $request->input('payment_method_type'));
        }

        // Filter by amount range
        if ($request->has('amount_min')) {
            $query->where('amount', '>=', (float) $request->input('amount_min'));
        }
        if ($request->has('amount_max')) {
            $query->where('amount', '<=', (float) $request->input('amount_max'));
        }

        // Filter by refunded status
        if ($request->has('refunded')) {
            $query->where('amount_refunded', '>', 0);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }
        if ($request->has('paid_from')) {
            $query->where('paid_at', '>=', $request->input('paid_from'));
        }
        if ($request->has('paid_to')) {
            $query->where('paid_at', '<=', $request->input('paid_to'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min((int) $request->input('per_page', 30), 100);
        $page = (int) $request->input('page', 1);

        $payments = $query->paginate($perPage, ['*'], 'page', $page);

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
     * Get order details.
     */
    public function show(string $id): JsonResponse
    {
        $payment = Payment::with(['customer', 'formation', 'enrollment'])->findOrFail($id);

        return ApiResponseBuilder::success(PaymentResource::make($payment));
    }

    /**
     * Process a refund for an order.
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'in:duplicate,fraudulent,requested_by_customer,expired'],
        ]);

        $result = $this->refundAction->__invoke(
            $payment,
            $validated['amount'] ?? null,
            $validated['reason'] ?? null
        );

        return ApiResponseBuilder::success([
            'payment' => PaymentResource::make($result['payment']),
            'stripe_refund' => [
                'id' => $result['stripe_refund']->id,
                'amount' => (float) ($result['stripe_refund']->amount / 100),
                'currency' => $result['stripe_refund']->currency,
                'status' => $result['stripe_refund']->status,
                'created' => $result['stripe_refund']->created,
            ],
        ], 'Refund processed successfully');
    }

    /**
     * Get order statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->query('period', '30d');
        $startDate = $this->getStartDate($period);

        $baseQuery = Payment::when($startDate, fn ($q) => $q->where('created_at', '>=', $startDate));

        $totalOrders = $baseQuery->count();
        $completedOrders = (clone $baseQuery)->where('status', \App\Enums\PaymentStatus::COMPLETED)->count();
        $pendingOrders = (clone $baseQuery)->where('status', \App\Enums\PaymentStatus::PENDING)->count();
        $failedOrders = (clone $baseQuery)->where('status', \App\Enums\PaymentStatus::FAILED)->count();
        $refundedOrders = (clone $baseQuery)->whereIn('status', [\App\Enums\PaymentStatus::REFUNDED, \App\Enums\PaymentStatus::PARTIALLY_REFUNDED])->count();

        $totalRevenue = (clone $baseQuery)->where('status', \App\Enums\PaymentStatus::COMPLETED)->sum('amount');
        $totalRefunded = (clone $baseQuery)->whereIn('status', [\App\Enums\PaymentStatus::REFUNDED, \App\Enums\PaymentStatus::PARTIALLY_REFUNDED])->sum('amount_refunded');

        $averageOrderValue = $completedOrders > 0 ? $totalRevenue / $completedOrders : 0;

        // By payment method
        $byPaymentMethod = (clone $baseQuery)
            ->where('status', \App\Enums\PaymentStatus::COMPLETED)
            ->selectRaw('payment_method_type, COUNT(*) as count, SUM(amount) as revenue')
            ->whereNotNull('payment_method_type')
            ->groupBy('payment_method_type')
            ->get();

        // By status
        $byStatus = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('status')
            ->get();

        return ApiResponseBuilder::success([
            'summary' => [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'pending_orders' => $pendingOrders,
                'failed_orders' => $failedOrders,
                'refunded_orders' => $refundedOrders,
            ],
            'revenue' => [
                'total' => (float) $totalRevenue,
                'refunded' => (float) $totalRefunded,
                'net' => (float) ($totalRevenue - $totalRefunded),
                'average_order_value' => (float) $averageOrderValue,
            ],
            'by_payment_method' => $byPaymentMethod->map(
                /** @return array<string, mixed> */
                fn ($stat) => [
                    'method' => $stat->payment_method_type,
                    'count' => $stat->count,
                    'revenue' => (float) $stat->revenue,
                ]
            ),
            'by_status' => $byStatus->map(
                /** @return array<string, mixed> */
                fn ($stat) => [
                    'status' => $stat->status,
                    'count' => $stat->count,
                    'total' => (float) $stat->total,
                ]
            ),
        ]);
    }

    /**
     * Get start date based on period.
     */
    private function getStartDate(string $period): ?\Carbon\Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            'all' => null,
            default => now()->subDays(30),
        };
    }
}
