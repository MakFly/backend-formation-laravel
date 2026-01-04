<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\LessonProgress\CompleteLessonAction;
use App\Actions\LessonProgress\StartLessonAction;
use App\Actions\LessonProgress\ToggleFavoriteLessonAction;
use App\Actions\LessonProgress\UpdateLessonNotesAction;
use App\Actions\LessonProgress\UpdateLessonProgressAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLessonProgressRequest;
use App\Http\Resources\LessonProgressCollection;
use App\Http\Resources\LessonProgressResource;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Support\Http\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProgressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LessonProgress::with(['enrollment', 'lesson']);

        // Filters
        if ($request->has('enrollment_id')) {
            $query->where('enrollment_id', $request->input('enrollment_id'));
        }
        if ($request->has('lesson_id')) {
            $query->where('lesson_id', $request->input('lesson_id'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $progress = $query->latest('last_accessed_at')->paginate(30);

        return ApiResponseBuilder::success(
            new LessonProgressCollection($progress),
            'Lesson progress retrieved successfully'
        );
    }

    public function getByEnrollment(string $enrollmentId): JsonResponse
    {
        $progress = LessonProgress::with(['lesson.module'])
            ->where('enrollment_id', $enrollmentId)
            ->latest('last_accessed_at')
            ->get();

        return ApiResponseBuilder::success(
            LessonProgressResource::collection($progress),
            'Enrollment progress retrieved successfully'
        );
    }

    public function show(string $id): JsonResponse
    {
        $progress = LessonProgress::with(['enrollment', 'lesson'])->findOrFail($id);

        return ApiResponseBuilder::success(
            new LessonProgressResource($progress),
            'Lesson progress retrieved successfully'
        );
    }

    public function start(
        string $enrollmentId,
        string $lessonId,
        StartLessonAction $action,
        Request $request
    ): JsonResponse {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        $lesson = Lesson::findOrFail($lessonId);

        $position = $request->input('position');

        $progress = $action($enrollment, $lesson, $position);

        return ApiResponseBuilder::success(
            new LessonProgressResource($progress),
            'Lesson started successfully'
        );
    }

    public function update(
        string $enrollmentId,
        string $lessonId,
        UpdateLessonProgressRequest $request,
        UpdateLessonProgressAction $action
    ): JsonResponse {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        $lesson = Lesson::findOrFail($lessonId);

        $progress = $action($enrollment, $lesson, $request->validated());

        return ApiResponseBuilder::success(
            new LessonProgressResource($progress),
            'Lesson progress updated successfully'
        );
    }

    public function complete(
        string $enrollmentId,
        string $lessonId,
        CompleteLessonAction $action
    ): JsonResponse {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        $lesson = Lesson::findOrFail($lessonId);

        $progress = $action($enrollment, $lesson);

        return ApiResponseBuilder::success(
            new LessonProgressResource($progress),
            'Lesson marked as completed'
        );
    }

    public function toggleFavorite(
        string $enrollmentId,
        string $lessonId,
        ToggleFavoriteLessonAction $action
    ): JsonResponse {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        $lesson = Lesson::findOrFail($lessonId);

        $progress = $action($enrollment, $lesson);

        return ApiResponseBuilder::success(
            new LessonProgressResource($progress),
            'Lesson favorite toggled successfully'
        );
    }

    public function updateNotes(
        string $enrollmentId,
        string $lessonId,
        Request $request,
        UpdateLessonNotesAction $action
    ): JsonResponse {
        $request->validate([
            'highlights' => 'sometimes|array',
            'bookmarks' => 'sometimes|array',
            'personal_notes' => 'sometimes|string',
        ]);

        $enrollment = Enrollment::findOrFail($enrollmentId);
        $lesson = Lesson::findOrFail($lessonId);

        $progress = $action($enrollment, $lesson, $request->only(['highlights', 'bookmarks', 'personal_notes']));

        return ApiResponseBuilder::success(
            new LessonProgressResource($progress),
            'Lesson notes updated successfully'
        );
    }
}
