<?php

declare(strict_types=1);

namespace App\Actions\LessonProgress;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use RuntimeException;

final readonly class UpdateLessonProgressAction
{
    /**
     * Update progress for a lesson.
     *
     * @param array{progress_percentage: int, current_position?: int|null, time_spent_seconds?: int} $data
     */
    public function __invoke(
        Enrollment $enrollment,
        Lesson $lesson,
        array $data
    ): LessonProgress {
        // Verify lesson belongs to enrollment's formation
        if ($lesson->formation_id !== $enrollment->formation_id) {
            throw new RuntimeException('Lesson does not belong to the enrolled formation');
        }

        // Get or create progress record
        $progress = LessonProgress::firstOrCreate([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ]);

        // Refresh to ensure enum casts are applied
        $progress = $progress->fresh();

        // Update progress
        $progress->updateProgress(
            $data['progress_percentage'],
            $data['current_position'] ?? null
        );

        // Add time spent if provided
        if (isset($data['time_spent_seconds'])) {
            $progress->addTimeSpent($data['time_spent_seconds']);
        }

        // Refresh enrollment progress
        $enrollment->refreshProgress();

        return $progress->fresh()->load(['lesson', 'enrollment']);
    }
}
