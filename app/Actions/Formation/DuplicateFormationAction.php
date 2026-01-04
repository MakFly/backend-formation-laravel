<?php

declare(strict_types=1);

namespace App\Actions\Formation;

use App\Models\Formation;
use Illuminate\Support\Facades\DB;

final readonly class DuplicateFormationAction
{
    /**
     * Duplicate a formation with all its modules and lessons.
     */
    public function __invoke(Formation $formation): Formation
    {
        return DB::transaction(function () use ($formation) {
            // Duplicate formation
            $duplicate = $formation->replicate();
            $duplicate->title = "Copy of {$formation->title}";
            $duplicate->slug = null; // Will be regenerated
            $duplicate->is_published = false;
            $duplicate->published_at = null;
            $duplicate->enrollment_count = 0;
            $duplicate->average_rating = null;
            $duplicate->review_count = 0;
            $duplicate->save();

            // Duplicate modules and lessons
            foreach ($formation->modules as $module) {
                $duplicateModule = $module->replicate();
                $duplicateModule->formation_id = $duplicate->id;
                $duplicateModule->save();

                // Duplicate lessons
                foreach ($module->lessons as $lesson) {
                    $duplicateLesson = $lesson->replicate();
                    $duplicateLesson->formation_id = $duplicate->id;
                    $duplicateLesson->module_id = $duplicateModule->id;
                    $duplicateLesson->save();
                }
            }

            return $duplicate->load(['modules.lessons']);
        });
    }
}
