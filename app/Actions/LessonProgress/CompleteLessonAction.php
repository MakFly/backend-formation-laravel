<?php

declare(strict_types=1);

namespace App\Actions\LessonProgress;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use RuntimeException;

final readonly class CompleteLessonAction
{
    /**
     * Mark a lesson as completed for an enrollment.
     */
    public function __invoke(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        // Verify lesson belongs to enrollment's formation
        if ($lesson->formation_id !== $enrollment->formation_id) {
            throw new RuntimeException('Lesson does not belong to the enrolled formation');
        }

        // Get or create progress record
        $progress = LessonProgress::firstOrCreate([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ]);

        // Mark as completed
        $progress->markAsCompleted();

        // Refresh enrollment progress
        $enrollment->refreshProgress();

        return $progress->fresh()->load(['lesson', 'enrollment']);
    }
}
