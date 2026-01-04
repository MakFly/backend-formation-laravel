<?php

declare(strict_types=1);

namespace App\Actions\LessonProgress;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use RuntimeException;

final readonly class StartLessonAction
{
    /**
     * Start a lesson for an enrollment.
     * Creates or updates lesson progress record.
     */
    public function __invoke(Enrollment $enrollment, Lesson $lesson, ?int $position = null): LessonProgress
    {
        // Verify lesson belongs to enrollment's formation
        if ($lesson->formation_id !== $enrollment->formation_id) {
            throw new RuntimeException('Lesson does not belong to the enrolled formation');
        }

        // Get or create progress record
        $progress = LessonProgress::firstOrNew([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ]);

        $progress->recordAccess($position);

        // Update enrollment access info
        $enrollment->recordAccess();

        return $progress->fresh()->load(['lesson', 'enrollment']);
    }
}
