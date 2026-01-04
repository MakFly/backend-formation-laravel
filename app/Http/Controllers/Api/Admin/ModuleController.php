<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleResource;
use App\Models\Formation;
use App\Models\Module;
use App\Models\Lesson;
use App\Support\Http\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ModuleController extends Controller
{
    /**
     * List modules for a formation.
     */
    public function index(Request $request, string $formationId): JsonResponse
    {
        $formation = Formation::findOrFail($formationId);
        $query = $formation->modules()->with('lessons');

        // Filter by published status
        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $modules = $query->orderBy('order')->get();

        return ApiResponseBuilder::success(ModuleResource::collection($modules));
    }

    /**
     * Get module details.
     */
    public function show(string $id): JsonResponse
    {
        $module = Module::with(['formation', 'lessons' => fn ($q) => $q->orderBy('order')])
            ->findOrFail($id);

        // Add stats
        $module->lessons_count = $module->lessons()->count();
        $module->total_duration = $module->lessons()->sum('duration_seconds');
        $module->published_lessons_count = $module->lessons()->where('is_published', true)->count();

        return ApiResponseBuilder::success(ModuleResource::make($module));
    }

    /**
     * Create a new module.
     */
    public function store(Request $request, string $formationId): JsonResponse
    {
        $formation = Formation::findOrFail($formationId);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:modules,slug'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:video,text,quiz,assignment,interactive'],
            'is_published' => ['boolean'],
            'is_free' => ['boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['formation_id'] = $formationId;
        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['title']);
        $validated['is_published'] = $validated['is_published'] ?? false;
        $validated['is_free'] = $validated['is_free'] ?? false;

        // Auto-set order if not provided
        if (!isset($validated['order'])) {
            $maxOrder = $formation->modules()->max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        }

        $module = Module::create($validated);

        return ApiResponseBuilder::created(ModuleResource::make($module), 'Module created successfully');
    }

    /**
     * Update a module.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $module = Module::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:modules,slug,' . $id],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:video,text,quiz,assignment,interactive'],
            'is_published' => ['boolean'],
            'is_free' => ['boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Auto-generate slug if title changed and slug not provided
        if (isset($validated['title']) && !isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        // Handle order change
        if (isset($validated['order']) && $validated['order'] !== $module->order) {
            $this->reorderModules($module->formation_id, $id, (int) $validated['order']);
        }

        $module->update($validated);

        return ApiResponseBuilder::success(ModuleResource::make($module->fresh()), 'Module updated successfully');
    }

    /**
     * Delete a module.
     */
    public function destroy(string $id): JsonResponse
    {
        $module = Module::with('lessons')->findOrFail($id);

        // Check if module has lessons
        if ($module->lessons()->count() > 0) {
            return ApiResponseBuilder::error('Cannot delete module with lessons. Delete or reassign lessons first.', \App\Enums\HttpStatus::BAD_REQUEST);
        }

        $module->delete();

        return ApiResponseBuilder::noContent();
    }

    /**
     * Reorder modules.
     */
    public function reorder(Request $request, string $formationId): JsonResponse
    {
        $validated = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*.id' => ['required', 'uuid', 'exists:modules,id'],
            'modules.*.order' => ['required', 'integer', 'min:0'],
        ]);

        Formation::findOrFail($formationId);

        foreach ($validated['modules'] as $moduleData) {
            $module = Module::where('formation_id', $formationId)
                ->where('id', $moduleData['id'])
                ->first();

            if ($module) {
                $module->update(['order' => $moduleData['order']]);
            }
        }

        return ApiResponseBuilder::success(null, 'Modules reordered successfully');
    }

    /**
     * Publish a module.
     */
    public function publish(string $id): JsonResponse
    {
        $module = Module::findOrFail($id);

        if ($module->is_published) {
            return ApiResponseBuilder::error('Module is already published', 'MODULE_ALREADY_PUBLISHED');
        }

        $module->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        return ApiResponseBuilder::success(ModuleResource::make($module->fresh()), 'Module published successfully');
    }

    /**
     * Unpublish a module.
     */
    public function unpublish(string $id): JsonResponse
    {
        $module = Module::findOrFail($id);
        $module->update(['is_published' => false]);

        return ApiResponseBuilder::success(ModuleResource::make($module->fresh()), 'Module unpublished successfully');
    }

    /**
     * Get module lessons.
     */
    public function lessons(string $id): JsonResponse
    {
        $module = Module::findOrFail($id);
        $lessons = $module->lessons()->orderBy('order')->get();

        return ApiResponseBuilder::success(\App\Http\Resources\LessonResource::collection($lessons));
    }

    /**
     * Handle module order change.
     */
    private function reorderModules(string $formationId, string $currentModuleId, int $newOrder): void
    {
        $currentModule = Module::where('formation_id', $formationId)
            ->where('id', $currentModuleId)
            ->first();

        if (!$currentModule) {
            return;
        }

        $oldOrder = $currentModule->order;

        if ($newOrder > $oldOrder) {
            // Moving down: decrement modules between old and new position
            Module::where('formation_id', $formationId)
                ->where('id', '!=', $currentModuleId)
                ->whereBetween('order', [$oldOrder, $newOrder])
                ->decrement('order');
        } else {
            // Moving up: increment modules between new and old position
            Module::where('formation_id', $formationId)
                ->where('id', '!=', $currentModuleId)
                ->whereBetween('order', [$newOrder, $oldOrder])
                ->increment('order');
        }
    }
}
