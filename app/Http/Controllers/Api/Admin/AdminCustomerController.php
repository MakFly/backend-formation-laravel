<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Support\Http\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminCustomerController extends Controller
{
    /**
     * List customers with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        // Search filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Filter by customer type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by company (for business customers)
        if ($request->has('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        // Filter by email verified
        if ($request->has('email_verified')) {
            $query->whereNotNull('email_verified_at');
        }

        // Filter by creation date range
        if ($request->has('created_from')) {
            $query->where('created_at', '>=', $request->input('created_from'));
        }
        if ($request->has('created_to')) {
            $query->where('created_at', '<=', $request->input('created_to'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min((int) $request->input('per_page', 30), 100);
        $page = (int) $request->input('page', 1);

        $customers = $query->paginate($perPage, ['*'], 'page', $page);

        // Enrich with stats if requested
        $includeStats = $request->boolean('include_stats', false);
        if ($includeStats) {
            $customers->getCollection()->each(function ($customer) {
                $customer->enrollments_count = $customer->enrollments()->count();
                $customer->total_spent = $customer->payments()->where('status', \App\Enums\PaymentStatus::COMPLETED)->sum('amount');
            });
        }

        return ApiResponseBuilder::success([
            'data' => CustomerResource::collection($customers),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
            ],
        ]);
    }

    /**
     * Get customer details.
     */
    public function show(string $id): JsonResponse
    {
        $customer = Customer::with(['enrollments.formation', 'payments.formation'])->findOrFail($id);

        // Add customer stats
        $customer->enrollments_count = $customer->enrollments()->count();
        $customer->active_enrollments_count = $customer->enrollments()->where('status', \App\Enums\EnrollmentStatus::ACTIVE)->count();
        $customer->completed_enrollments_count = $customer->enrollments()->where('status', \App\Enums\EnrollmentStatus::COMPLETED)->count();
        $customer->total_spent = $customer->payments()->where('status', \App\Enums\PaymentStatus::COMPLETED)->sum('amount');
        $customer->last_payment = $customer->payments()->where('status', \App\Enums\PaymentStatus::COMPLETED)->orderBy('paid_at', 'desc')->first();

        return ApiResponseBuilder::success(CustomerResource::make($customer));
    }

    /**
     * Create a new customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        return ApiResponseBuilder::created(CustomerResource::make($customer), 'Customer created successfully');
    }

    /**
     * Update a customer.
     */
    public function update(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->update($request->validated());

        return ApiResponseBuilder::success(CustomerResource::make($customer), 'Customer updated successfully');
    }

    /**
     * Delete a customer.
     */
    public function destroy(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        // Check if customer has enrollments or payments
        $hasEnrollments = $customer->enrollments()->exists();
        $hasPayments = $customer->payments()->where('status', \App\Enums\PaymentStatus::COMPLETED)->exists();

        if ($hasEnrollments || $hasPayments) {
            return ApiResponseBuilder::error('Cannot delete customer with enrollments or payments', 'CUSTOMER_HAS_DATA');
        }

        $customer->delete();

        return ApiResponseBuilder::noContent();
    }

    /**
     * Get customer enrollments.
     */
    public function enrollments(string $id, Request $request): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $query = $customer->enrollments()->with('formation');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 30), 100);
        $enrollments = $query->paginate($perPage);

        return ApiResponseBuilder::success([
            'data' => \App\Http\Resources\EnrollmentResource::collection($enrollments),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    /**
     * Get customer payments.
     */
    public function payments(string $id, Request $request): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $query = $customer->payments()->with(['formation', 'enrollment']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min((int) $request->input('per_page', 30), 100);
        $payments = $query->paginate($perPage);

        return ApiResponseBuilder::success([
            'data' => \App\Http\Resources\PaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Get customer statistics.
     */
    public function stats(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $enrollmentsCount = $customer->enrollments()->count();
        $activeEnrollments = $customer->enrollments()->where('status', \App\Enums\EnrollmentStatus::ACTIVE)->count();
        $completedEnrollments = $customer->enrollments()->where('status', \App\Enums\EnrollmentStatus::COMPLETED)->count();

        $totalSpent = $customer->payments()->where('status', \App\Enums\PaymentStatus::COMPLETED)->sum('amount');
        $totalRefunded = $customer->payments()->whereIn('status', [\App\Enums\PaymentStatus::REFUNDED, \App\Enums\PaymentStatus::PARTIALLY_REFUNDED])->sum('amount_refunded');

        $lastEnrollment = $customer->enrollments()->orderBy('created_at', 'desc')->first();
        $lastPayment = $customer->payments()->orderBy('created_at', 'desc')->first();

        return ApiResponseBuilder::success([
            'enrollments' => [
                'total' => $enrollmentsCount,
                'active' => $activeEnrollments,
                'completed' => $completedEnrollments,
                'last_at' => $lastEnrollment?->created_at?->toIso8601String(),
            ],
            'payments' => [
                'total_spent' => (float) $totalSpent,
                'total_refunded' => (float) $totalRefunded,
                'net_amount' => (float) ($totalSpent - $totalRefunded),
                'last_at' => $lastPayment?->created_at?->toIso8601String(),
            ],
        ]);
    }
}
