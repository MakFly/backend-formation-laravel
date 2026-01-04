<?php

declare(strict_types=1);

namespace App\Actions\Enrollment;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;

final readonly class CheckLessonAccessAction
{
    /**
     * Check if a customer can access a specific lesson through their enrollment.
     *
     * @return array{accessible: bool, reason?: string}
     */
    public function __invoke(Enrollment $enrollment, Lesson $lesson): array
    {
        // Check if enrollment is active
        if (! $enrollment->isActive()) {
            if ($enrollment->isPending()) {
                return ['accessible' => false, 'reason' => 'Enrollment is pending activation'];
            }
            if ($enrollment->status->value === 'cancelled') {
                return ['accessible' => false, 'reason' => 'Enrollment has been cancelled'];
            }
            if ($enrollment->status->value === 'suspended') {
                return ['accessible' => false, 'reason' => 'Enrollment has been suspended'];
            }

            return ['accessible' => false, 'reason' => 'Enrollment is not active'];
        }

        // Check if lesson belongs to the enrolled formation
        if ($lesson->formation_id !== $enrollment->formation_id) {
            return ['accessible' => false, 'reason' => 'Lesson does not belong to the enrolled formation'];
        }

        // Check if lesson is published
        if (! $lesson->is_published) {
            return ['accessible' => false, 'reason' => 'Lesson is not published'];
        }

        // Check module order - user must complete previous modules first
        /** @var Module|null $lessonModule */
        $lessonModule = $lesson->module;
        if ($lessonModule === null) {
            return ['accessible' => false, 'reason' => 'Lesson is not assigned to a module'];
        }

        // Get all modules in the formation
        /** @var \Illuminate\Database\Eloquent\Collection<int, Module> $modules */
        $modules = $enrollment->formation->modules()->ordered()->get();
        $currentModuleIndex = $modules->search(fn ($m) => $m->id === $lessonModule->id);

        if ($currentModuleIndex === false) {
            return ['accessible' => false, 'reason' => 'Module not found in formation'];
        }

        // Check if there are previous modules that need to be completed
        if ($currentModuleIndex > 0) {
            for ($i = 0; $i < $currentModuleIndex; $i++) {
                $previousModule = $modules[$i];
                $previousModuleCompleted = $this->isModuleCompleted($enrollment, $previousModule);

                if (! $previousModuleCompleted) {
                    return [
                        'accessible' => false,
                        'reason' => 'Previous modules must be completed first',
                        'blocked_by' => $previousModule->title,
                    ];
                }
            }
        }

        // Check if lesson is preview (always accessible)
        if ($lesson->is_preview) {
            return ['accessible' => true];
        }

        return ['accessible' => true];
    }

    private function isModuleCompleted(Enrollment $enrollment, Module $module): bool
    {
        $moduleLessonIds = $module->lessons()->pluck('id');
        $completedCount = $enrollment->lessonProgress()
            ->whereIn('lesson_id', $moduleLessonIds)
            ->where('status', 'completed')
            ->count();

        return $completedCount === $moduleLessonIds->count();
    }
}
