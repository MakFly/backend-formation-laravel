<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\Module;
use App\Support\Http\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class LessonController extends Controller
{
    /**
     * List lessons for a module or formation.
     */
    public function index(Request $request, ?string $moduleId = null): JsonResponse
    {
        if ($moduleId) {
            $module = Module::findOrFail($moduleId);
            $query = $module->lessons();
        } elseif ($request->has('formation_id')) {
            $formation = Formation::findOrFail($request->input('formation_id'));
            $query = Lesson::where('formation_id', $formation->id);
        } else {
            return ApiResponseBuilder::error('Either module_id or formation_id is required', 'MISSING_PARAMETER');
        }

        // Filter by published status
        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        // Filter by preview
        if ($request->has('is_preview')) {
            $query->where('is_preview', $request->boolean('is_preview'));
        }

        $lessons = $query->orderBy('order')->get();

        return ApiResponseBuilder::success(LessonResource::collection($lessons));
    }

    /**
     * Get lesson details.
     */
    public function show(string $id): JsonResponse
    {
        $lesson = Lesson::with(['module', 'formation', 'resources'])
            ->findOrFail($id);

        // Add stats
        $lesson->resources_count = $lesson->resources()->count();

        return ApiResponseBuilder::success(LessonResource::make($lesson));
    }

    /**
     * Create a new lesson.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => ['nullable', 'uuid', 'exists:modules,id'],
            'formation_id' => ['required', 'uuid', 'exists:formations,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:lessons,slug'],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'video_url' => ['nullable', 'url'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_preview' => ['boolean'],
            'is_published' => ['boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['title']);
        $validated['is_preview'] = $validated['is_preview'] ?? false;
        $validated['is_published'] = $validated['is_published'] ?? false;

        // Auto-set order if not provided
        if (! isset($validated['order'])) {
            if (isset($validated['module_id'])) {
                $maxOrder = Lesson::where('module_id', $validated['module_id'])->max('order') ?? 0;
            } else {
                $maxOrder = Lesson::where('formation_id', $validated['formation_id'])->max('order') ?? 0;
            }
            $validated['order'] = $maxOrder + 1;
        }

        $lesson = Lesson::create($validated);

        return ApiResponseBuilder::created(LessonResource::make($lesson), 'Lesson created successfully');
    }

    /**
     * Update a lesson.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);

        $validated = $request->validate([
            'module_id' => ['nullable', 'uuid', 'exists:modules,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:lessons,slug,'.$id],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'video_url' => ['nullable', 'url'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_preview' => ['boolean'],
            'is_published' => ['boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Auto-generate slug if title changed and slug not provided
        if (isset($validated['title']) && ! isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        // Handle order change
        if (isset($validated['order']) && $validated['order'] !== $lesson->order) {
            $scopeField = $lesson->module_id ?? null;
            $this->reorderLessons($lesson->formation_id, $scopeField, $id, (int) $validated['order']);
        }

        $lesson->update($validated);

        return ApiResponseBuilder::success(LessonResource::make($lesson->fresh()), 'Lesson updated successfully');
    }

    /**
     * Delete a lesson.
     */
    public function destroy(string $id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);

        // Check for associated resources
        if ($lesson->resources()->count() > 0) {
            // Delete resources and files
            foreach ($lesson->resources as $resource) {
                if ($resource->file_path && Storage::exists($resource->file_path)) {
                    Storage::delete($resource->file_path);
                }
                $resource->delete();
            }
        }

        // Delete lesson thumbnail
        if ($lesson->thumbnail && Storage::exists($lesson->thumbnail)) {
            Storage::delete($lesson->thumbnail);
        }

        $lesson->delete();

        return ApiResponseBuilder::noContent();
    }

    /**
     * Upload lesson content (video, PDF, images).
     */
    public function uploadContent(Request $request, string $id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:512000'], // 500MB max
            'type' => ['required', 'in:video,pdf,image,document,attachment'],
        ]);

        $file = $validated['file'];
        $type = $validated['type'];

        $path = $this->storeFile($file, $type, $lesson->formation_id, $lesson->id);

        // Update lesson based on file type
        $updateData = [];
        if ($type === 'video') {
            $updateData['video_url'] = Storage::url($path);
        } elseif ($type === 'image') {
            $updateData['thumbnail'] = $path;
        }

        $lesson->update($updateData);

        return ApiResponseBuilder::success([
            'path' => $path,
            'url' => Storage::url($path),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ], 'File uploaded successfully');
    }

    /**
     * Upload lesson thumbnail.
     */
    public function uploadThumbnail(Request $request, string $id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);

        $validated = $request->validate([
            'file' => ['required', 'image', 'max:10240'], // 10MB max
        ]);

        $file = $validated['file'];

        // Delete old thumbnail if exists
        if ($lesson->thumbnail && Storage::exists($lesson->thumbnail)) {
            Storage::delete($lesson->thumbnail);
        }

        $path = $file->store("lessons/{$lesson->formation_id}/{$lesson->id}", 'public');

        $lesson->update(['thumbnail' => $path]);

        return ApiResponseBuilder::success([
            'path' => $path,
            'url' => Storage::url($path),
        ], 'Thumbnail uploaded successfully');
    }

    /**
     * Reorder lessons.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => ['nullable', 'uuid', 'exists:modules,id'],
            'formation_id' => ['required', 'uuid', 'exists:formations,id'],
            'lessons' => ['required', 'array'],
            'lessons.*.id' => ['required', 'uuid', 'exists:lessons,id'],
            'lessons.*.order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['lessons'] as $lessonData) {
            $lesson = Lesson::where('formation_id', $validated['formation_id'])
                ->where('id', $lessonData['id']);

            if (isset($validated['module_id'])) {
                $lesson->where('module_id', $validated['module_id']);
            }

            $lesson = $lesson->first();

            if ($lesson) {
                $lesson->update(['order' => $lessonData['order']]);
            }
        }

        return ApiResponseBuilder::success(null, 'Lessons reordered successfully');
    }

    /**
     * Publish a lesson.
     */
    public function publish(string $id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);

        if ($lesson->is_published) {
            return ApiResponseBuilder::error('Lesson is already published', 'LESSON_ALREADY_PUBLISHED');
        }

        $lesson->update(['is_published' => true]);

        return ApiResponseBuilder::success(LessonResource::make($lesson->fresh()), 'Lesson published successfully');
    }

    /**
     * Unpublish a lesson.
     */
    public function unpublish(string $id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->update(['is_published' => false]);

        return ApiResponseBuilder::success(LessonResource::make($lesson->fresh()), 'Lesson unpublished successfully');
    }

    /**
     * Get lesson resources.
     */
    public function resources(string $id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);
        $resources = $lesson->resources()->orderBy('order')->get();

        return ApiResponseBuilder::success(\App\Http\Resources\LessonResourceResource::collection($resources));
    }

    /**
     * Store uploaded file.
     */
    private function storeFile(UploadedFile $file, string $type, string $formationId, string $lessonId): string
    {
        $folder = match ($type) {
            'video' => 'videos',
            'pdf', 'document' => 'documents',
            'image' => 'images',
            'attachment' => 'attachments',
            default => 'files',
        };

        return $file->store("formations/{$formationId}/lessons/{$lessonId}/{$folder}", 'public');
    }

    /**
     * Handle lesson order change.
     */
    private function reorderLessons(string $formationId, ?string $moduleId, string $currentLessonId, int $newOrder): void
    {
        $currentLesson = Lesson::where('formation_id', $formationId)
            ->where('id', $currentLessonId);

        if ($moduleId) {
            $currentLesson->where('module_id', $moduleId);
        }

        $currentLesson = $currentLesson->first();

        if (! $currentLesson) {
            return;
        }

        $oldOrder = $currentLesson->order;

        $query = Lesson::where('formation_id', $formationId);
        if ($moduleId) {
            $query->where('module_id', $moduleId);
        }
        $query->where('id', '!=', $currentLessonId);

        if ($newOrder > $oldOrder) {
            // Moving down: decrement lessons between old and new position
            $query->whereBetween('order', [$oldOrder, $newOrder])->decrement('order');
        } else {
            // Moving up: increment lessons between new and old position
            $query->whereBetween('order', [$newOrder, $oldOrder])->increment('order');
        }
    }
}
