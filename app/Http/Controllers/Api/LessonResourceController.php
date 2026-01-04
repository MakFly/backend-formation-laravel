<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\LessonResource\CreateLessonResourceAction;
use App\Actions\LessonResource\DeleteLessonResourceAction;
use App\Actions\LessonResource\ReorderLessonResourcesAction;
use App\Actions\LessonResource\UpdateLessonResourceAction;
use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLessonResourceRequest;
use App\Http\Requests\UpdateLessonResourceRequest;
use App\Http\Resources\LessonResourceCollection;
use App\Http\Resources\LessonResourceResource;
use App\Models\Lesson;
use App\Models\LessonResource as LessonResourceModel;
use App\Support\Http\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LessonResourceController extends Controller
{
    public function index(Request $request, string $lessonId): JsonResponse
    {
        $resources = LessonResourceModel::where('lesson_id', $lessonId)
            ->ordered()
            ->get();

        return ApiResponseBuilder::success(
            new LessonResourceCollection($resources),
            'Lesson resources retrieved successfully',
            HttpStatus::OK
        );
    }

    public function store(StoreLessonResourceRequest $request, string $lessonId): JsonResponse
    {
        $lesson = Lesson::findOrFail($lessonId);
        $action = new CreateLessonResourceAction;
        $resource = $action($request->validated(), $lesson);

        return ApiResponseBuilder::success(
            new LessonResourceResource($resource),
            'Lesson resource created successfully',
            HttpStatus::CREATED
        );
    }

    public function show(string $lessonId, string $id): JsonResponse
    {
        $resource = LessonResourceModel::where('lesson_id', $lessonId)
            ->where('id', $id)
            ->firstOrFail();

        return ApiResponseBuilder::success(
            new LessonResourceResource($resource),
            'Lesson resource retrieved successfully'
        );
    }

    public function update(UpdateLessonResourceRequest $request, string $lessonId, string $id): JsonResponse
    {
        $resource = LessonResourceModel::where('lesson_id', $lessonId)
            ->where('id', $id)
            ->firstOrFail();

        $action = new UpdateLessonResourceAction;
        $resource = $action($resource, $request->validated());

        return ApiResponseBuilder::success(
            new LessonResourceResource($resource),
            'Lesson resource updated successfully'
        );
    }

    public function destroy(string $lessonId, string $id): JsonResponse
    {
        $resource = LessonResourceModel::where('lesson_id', $lessonId)
            ->where('id', $id)
            ->firstOrFail();

        $action = new DeleteLessonResourceAction;
        $action($resource);

        return ApiResponseBuilder::noContent();
    }

    public function reorder(Request $request, string $lessonId): JsonResponse
    {
        $request->validate([
            'resources' => ['required', 'array'],
            'resources.*.id' => ['required', 'string', 'exists:lesson_resources,id'],
            'resources.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $lesson = Lesson::findOrFail($lessonId);
        $action = new ReorderLessonResourcesAction;
        $action($lesson, $request->input('resources'));

        return ApiResponseBuilder::success(
            null,
            'Lesson resources reordered successfully'
        );
    }
}
