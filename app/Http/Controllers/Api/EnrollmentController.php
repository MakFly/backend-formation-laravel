<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Enrollment\CheckLessonAccessAction;
use App\Actions\Enrollment\CreateEnrollmentAction;
use App\Actions\Enrollment\ValidateEnrollmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Resources\EnrollmentCollection;
use App\Http\Resources\EnrollmentResource;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Support\Http\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Enrollment::with(['customer', 'formation']);

        // Filters
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }
        if ($request->has('formation_id')) {
            $query->where('formation_id', $request->input('formation_id'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $enrollments = $query->recent()->paginate(30);

        return ApiResponseBuilder::success(
            new EnrollmentCollection($enrollments),
            'Enrollments retrieved successfully'
        );
    }

    public function store(StoreEnrollmentRequest $request, CreateEnrollmentAction $action): JsonResponse
    {
        $customer = Customer::findOrFail($request->validated()['customer_id']);
        $formation = Formation::findOrFail($request->validated()['formation_id']);

        $enrollment = $action($customer, $formation, $request->validated());

        return ApiResponseBuilder::created(
            new EnrollmentResource($enrollment),
            'Enrollment created successfully'
        );
    }

    public function show(string $id): JsonResponse
    {
        $enrollment = Enrollment::with(['customer', 'formation', 'lessonProgress'])->findOrFail($id);

        return ApiResponseBuilder::success(
            new EnrollmentResource($enrollment),
            'Enrollment retrieved successfully'
        );
    }

    public function getByCustomer(string $customerId): JsonResponse
    {
        $enrollments = Enrollment::with(['formation', 'lessonProgress'])
            ->where('customer_id', $customerId)
            ->recent()
            ->get();

        return ApiResponseBuilder::success(
            EnrollmentResource::collection($enrollments),
            'Customer enrollments retrieved successfully'
        );
    }

    public function getByFormation(string $formationId): JsonResponse
    {
        $enrollments = Enrollment::with(['customer'])
            ->where('formation_id', $formationId)
            ->recent()
            ->paginate(30);

        return ApiResponseBuilder::success(
            new EnrollmentCollection($enrollments),
            'Formation enrollments retrieved successfully'
        );
    }

    public function validate(string $id, ValidateEnrollmentAction $action): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);
        $validatedEnrollment = $action($enrollment);

        return ApiResponseBuilder::success(
            new EnrollmentResource($validatedEnrollment),
            'Enrollment validated and activated successfully'
        );
    }

    public function checkLessonAccess(
        string $enrollmentId,
        string $lessonId,
        CheckLessonAccessAction $action
    ): JsonResponse {
        $enrollment = Enrollment::with(['formation'])->findOrFail($enrollmentId);
        $lesson = $enrollment->formation->lessons()->findOrFail($lessonId);

        $result = $action($enrollment, $lesson);

        if (! $result['accessible']) {
            return ApiResponseBuilder::error(
                $result['reason'],
                'LESSON_ACCESS_DENIED',
                ['blocked_by' => $result['blocked_by'] ?? null]
            )->withStatusCode(403);
        }

        return ApiResponseBuilder::success(
            ['accessible' => true, 'lesson' => $lesson],
            'Lesson access granted'
        );
    }

    public function cancel(string $id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);
        $enrollment->markAsCancelled();

        return ApiResponseBuilder::success(
            new EnrollmentResource($enrollment->fresh()),
            'Enrollment cancelled successfully'
        );
    }
}
