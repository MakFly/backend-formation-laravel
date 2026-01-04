<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Http\Resources\ModuleResource;
use App\Http\Resources\ModuleCollection;
use App\Actions\Module\CreateModuleAction;
use App\Actions\Module\UpdateModuleAction;
use App\Actions\Module\DeleteModuleAction;
use App\Actions\Module\ReorderModulesAction;
use App\Models\Formation;
use App\Models\Module;
use App\Support\Http\ApiResponseBuilder;
use App\Enums\HttpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ModuleController extends Controller
{
    public function index(Request $request, string $formationId): JsonResponse
    {
        $modules = Module::where('formation_id', $formationId)
            ->ordered()
            ->get();

        return ApiResponseBuilder::success(
            new ModuleCollection($modules),
            'Modules retrieved successfully',
            HttpStatus::OK
        );
    }

    public function store(StoreModuleRequest $request, string $formationId): JsonResponse
    {
        $formation = Formation::findOrFail($formationId);
        $action = new CreateModuleAction();
        $module = $action($request->validated(), $formation);

        return ApiResponseBuilder::success(
            new ModuleResource($module),
            'Module created successfully',
            HttpStatus::CREATED
        );
    }

    public function show(string $formationId, string $id): JsonResponse
    {
        $module = Module::where('formation_id', $formationId)
            ->where('id', $id)
            ->firstOrFail();

        return ApiResponseBuilder::success(
            new ModuleResource($module),
            'Module retrieved successfully'
        );
    }

    public function update(UpdateModuleRequest $request, string $formationId, string $id): JsonResponse
    {
        $module = Module::where('formation_id', $formationId)
            ->where('id', $id)
            ->firstOrFail();

        $action = new UpdateModuleAction();
        $module = $action($module, $request->validated());

        return ApiResponseBuilder::success(
            new ModuleResource($module),
            'Module updated successfully'
        );
    }

    public function destroy(string $formationId, string $id): JsonResponse
    {
        $module = Module::where('formation_id', $formationId)
            ->where('id', $id)
            ->firstOrFail();

        $action = new DeleteModuleAction();
        $action($module);

        return ApiResponseBuilder::noContent();
    }

    public function reorder(Request $request, string $formationId): JsonResponse
    {
        $request->validate([
            'modules' => ['required', 'array'],
            'modules.*.id' => ['required', 'string', 'exists:modules,id'],
            'modules.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $formation = Formation::findOrFail($formationId);
        $action = new ReorderModulesAction();
        $action($formation, $request->input('modules'));

        return ApiResponseBuilder::success(
            null,
            'Modules reordered successfully'
        );
    }
}
