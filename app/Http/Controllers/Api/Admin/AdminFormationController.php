<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Formation\DuplicateFormationAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\FormationResource;
use App\Models\Formation;
use App\Support\Http\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AdminFormationController extends Controller
{
    /**
     * List formations with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Formation::query();

        // Search filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by published status
        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        // Filter by pricing tier
        if ($request->has('pricing_tier')) {
            $query->where('pricing_tier', $request->input('pricing_tier'));
        }

        // Filter by mode
        if ($request->has('mode')) {
            $query->where('mode', $request->input('mode'));
        }

        // Filter by level
        if ($request->has('level')) {
            $query->where('level', $request->input('level'));
        }

        // Filter by language
        if ($request->has('language')) {
            $query->where('language', $request->input('language'));
        }

        // Filter by instructor
        if ($request->has('instructor_name')) {
            $query->where('instructor_name', 'like', '%'.$request->input('instructor_name').'%');
        }

        // Filter by price range
        if ($request->has('price_min')) {
            $query->where('price', '>=', (float) $request->input('price_min'));
        }
        if ($request->has('price_max')) {
            $query->where('price', '<=', (float) $request->input('price_max'));
        }

        // Filter by tags
        if ($request->has('tags')) {
            $tags = explode(',', $request->input('tags'));
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
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

        $formations = $query->paginate($perPage, ['*'], 'page', $page);

        // Enrich with stats if requested
        $includeStats = $request->boolean('include_stats', false);
        if ($includeStats) {
            $formations->getCollection()->each(function ($formation) {
                $formation->enrollments_count = $formation->enrollments()->count();
                $formation->active_enrollments_count = $formation->enrollments()->where('status', \App\Enums\EnrollmentStatus::ACTIVE)->count();
                $formation->completed_enrollments_count = $formation->enrollments()->where('status', \App\Enums\EnrollmentStatus::COMPLETED)->count();
                $formation->revenue = $formation->payments()->where('status', \App\Enums\PaymentStatus::COMPLETED)->sum('amount');
            });
        }

        return ApiResponseBuilder::success([
            'data' => FormationResource::collection($formations),
            'meta' => [
                'current_page' => $formations->currentPage(),
                'per_page' => $formations->perPage(),
                'total' => $formations->total(),
                'last_page' => $formations->lastPage(),
            ],
        ]);
    }

    /**
     * Get formation details.
     */
    public function show(string $id): JsonResponse
    {
        $formation = Formation::with(['modules.lessons', 'category', 'enrollments', 'payments'])
            ->findOrFail($id);

        // Add formation stats
        $formation->enrollments_count = $formation->enrollments()->count();
        $formation->active_enrollments_count = $formation->enrollments()->where('status', \App\Enums\EnrollmentStatus::ACTIVE)->count();
        $formation->completed_enrollments_count = $formation->enrollments()->where('status', \App\Enums\EnrollmentStatus::COMPLETED)->count();
        $formation->revenue = $formation->payments()->where('status', \App\Enums\PaymentStatus::COMPLETED)->sum('amount');
        $formation->refunds = $formation->payments()->whereIn('status', [\App\Enums\PaymentStatus::REFUNDED, \App\Enums\PaymentStatus::PARTIALLY_REFUNDED])->sum('amount_refunded');
        $formation->lessons_count = $formation->lessons()->count();
        $formation->modules_count = $formation->modules()->count();

        return ApiResponseBuilder::success(FormationResource::make($formation));
    }

    /**
     * Create a new formation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:formations,slug'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'pricing_tier' => ['nullable', 'in:free,premium,enterprise'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'mode' => ['nullable', 'in:online,onsite,hybrid'],
            'level' => ['nullable', 'in:beginner,intermediate,advanced,expert'],
            'language' => ['nullable', 'string', 'max:10'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
            'thumbnail_url' => ['nullable', 'url'],
            'video_url' => ['nullable', 'url'],
            'instructor_name' => ['nullable', 'string', 'max:255'],
            'instructor_bio' => ['nullable', 'string'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'requirements' => ['nullable', 'array'],
            'objectives' => ['nullable', 'array'],
            'audience' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['title']);
        $validated['is_published'] = $validated['is_published'] ?? false;
        $validated['is_featured'] = $validated['is_featured'] ?? false;
        $validated['pricing_tier'] = $validated['pricing_tier'] ?? \App\Enums\PricingTier::FREE;

        $formation = Formation::create($validated);

        return ApiResponseBuilder::created(FormationResource::make($formation), 'Formation created successfully');
    }

    /**
     * Update a formation.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $formation = Formation::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:formations,slug,'.$id],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'pricing_tier' => ['nullable', 'in:free,premium,enterprise'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'mode' => ['nullable', 'in:online,onsite,hybrid'],
            'level' => ['nullable', 'in:beginner,intermediate,advanced,expert'],
            'language' => ['nullable', 'string', 'max:10'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
            'thumbnail_url' => ['nullable', 'url'],
            'video_url' => ['nullable', 'url'],
            'instructor_name' => ['nullable', 'string', 'max:255'],
            'instructor_bio' => ['nullable', 'string'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'requirements' => ['nullable', 'array'],
            'objectives' => ['nullable', 'array'],
            'audience' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Auto-generate slug if title changed and slug not provided
        if (isset($validated['title']) && ! isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $formation->update($validated);

        return ApiResponseBuilder::success(FormationResource::make($formation), 'Formation updated successfully');
    }

    /**
     * Delete a formation.
     */
    public function destroy(string $id): JsonResponse
    {
        $formation = Formation::findOrFail($id);

        // Check if formation has enrollments or payments
        $hasEnrollments = $formation->enrollments()->exists();
        $hasPayments = $formation->payments()->where('status', \App\Enums\PaymentStatus::COMPLETED)->exists();

        if ($hasEnrollments || $hasPayments) {
            return ApiResponseBuilder::error('Cannot delete formation with enrollments or payments', 'FORMATION_HAS_DATA');
        }

        $formation->delete();

        return ApiResponseBuilder::noContent();
    }

    /**
     * Duplicate a formation.
     */
    public function duplicate(string $id): JsonResponse
    {
        $formation = Formation::findOrFail($id);
        $action = new DuplicateFormationAction;
        $duplicate = $action($formation);

        return ApiResponseBuilder::created(FormationResource::make($duplicate), 'Formation duplicated successfully');
    }

    /**
     * Publish a formation.
     */
    public function publish(string $id): JsonResponse
    {
        $formation = Formation::findOrFail($id);
        $formation->update(['is_published' => true, 'published_at' => now()]);

        return ApiResponseBuilder::success(FormationResource::make($formation), 'Formation published successfully');
    }

    /**
     * Unpublish a formation.
     */
    public function unpublish(string $id): JsonResponse
    {
        $formation = Formation::findOrFail($id);
        $formation->update(['is_published' => false]);

        return ApiResponseBuilder::success(FormationResource::make($formation), 'Formation unpublished successfully');
    }

    /**
     * Get formation enrollments.
     */
    public function enrollments(string $id, Request $request): JsonResponse
    {
        $formation = Formation::findOrFail($id);

        $query = $formation->enrollments()->with('customer');

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
     * Get formation payments.
     */
    public function payments(string $id, Request $request): JsonResponse
    {
        $formation = Formation::findOrFail($id);

        $query = $formation->payments()->with(['customer', 'enrollment']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

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
     * Get formation statistics.
     */
    public function stats(string $id): JsonResponse
    {
        $formation = Formation::findOrFail($id);

        $enrollmentsCount = $formation->enrollments()->count();
        $activeEnrollments = $formation->enrollments()->where('status', \App\Enums\EnrollmentStatus::ACTIVE)->count();
        $completedEnrollments = $formation->enrollments()->where('status', \App\Enums\EnrollmentStatus::COMPLETED)->count();

        $revenue = $formation->payments()->where('status', \App\Enums\PaymentStatus::COMPLETED)->sum('amount');
        $refunds = $formation->payments()->whereIn('status', [\App\Enums\PaymentStatus::REFUNDED, \App\Enums\PaymentStatus::PARTIALLY_REFUNDED])->sum('amount_refunded');

        $lessonsCount = $formation->lessons()->count();
        $modulesCount = $formation->modules()->count();

        return ApiResponseBuilder::success([
            'enrollments' => [
                'total' => $enrollmentsCount,
                'active' => $activeEnrollments,
                'completed' => $completedEnrollments,
                'completion_rate' => $enrollmentsCount > 0 ? round(($completedEnrollments / $enrollmentsCount) * 100, 2) : 0,
            ],
            'revenue' => [
                'total' => (float) $revenue,
                'refunded' => (float) $refunds,
                'net' => (float) ($revenue - $refunds),
            ],
            'content' => [
                'modules_count' => $modulesCount,
                'lessons_count' => $lessonsCount,
            ],
        ]);
    }
}
