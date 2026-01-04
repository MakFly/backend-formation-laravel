<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\LessonProgress;

use App\Actions\Enrollment\CreateEnrollmentAction;
use App\Actions\Enrollment\ValidateEnrollmentAction;
use App\Actions\LessonProgress\UpdateLessonProgressAction;
use App\Enums\LessonProgressStatus;
use App\Enums\PricingTier;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UpdateLessonProgressActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_lesson_progress(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();
        $lesson = Lesson::first();
        $action = new UpdateLessonProgressAction();

        $progress = $action($enrollment, $lesson, [
            'progress_percentage' => 50,
        ]);

        $this->assertEquals(50, $progress->progress_percentage);
        $this->assertEquals(LessonProgressStatus::IN_PROGRESS, $progress->status);
    }

    #[Test]
    public function it_marks_lesson_as_completed_when_100_percent(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();
        $lesson = Lesson::first();
        $action = new UpdateLessonProgressAction();

        $progress = $action($enrollment, $lesson, [
            'progress_percentage' => 100,
        ]);

        $this->assertEquals(100, $progress->progress_percentage);
        $this->assertEquals(LessonProgressStatus::COMPLETED, $progress->status);
        $this->assertNotNull($progress->completed_at);
    }

    #[Test]
    public function it_updates_current_position(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();
        $lesson = Lesson::first();
        $action = new UpdateLessonProgressAction();

        $progress = $action($enrollment, $lesson, [
            'progress_percentage' => 30,
            'current_position' => 180,
        ]);

        $this->assertEquals(180, $progress->current_position);
    }

    #[Test]
    public function it_adds_time_spent(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();
        $lesson = Lesson::first();
        $action = new UpdateLessonProgressAction();

        $progress = $action($enrollment, $lesson, [
            'progress_percentage' => 50,
            'time_spent_seconds' => 300,
        ]);

        $this->assertEquals(300, $progress->time_spent_seconds);
    }

    #[Test]
    public function it_refreshes_enrollment_progress(): void
    {
        $enrollment = $this->createEnrollmentWithMultipleLessons(2);

        // Complete first lesson
        $lesson1 = $enrollment->formation->lessons()->first();
        $action = new UpdateLessonProgressAction();
        $action($enrollment, $lesson1, ['progress_percentage' => 100]);

        // Enrollment progress should be 50% (1/2 lessons)
        $this->assertEquals(50, $enrollment->fresh()->progress_percentage);
    }

    private function createEnrollmentWithLesson(): Enrollment
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create(['pricing_tier' => PricingTier::FREE, 'price' => 0]);

        $module = Module::factory()->create(['formation_id' => $formation->id]);
        Lesson::factory()->create([
            'module_id' => $module->id,
            'formation_id' => $formation->id,
        ]);

        $enrollment = (new CreateEnrollmentAction())($customer, $formation);
        (new ValidateEnrollmentAction())($enrollment);

        return $enrollment->fresh();
    }

    private function createEnrollmentWithMultipleLessons(int $count): Enrollment
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create(['pricing_tier' => PricingTier::FREE, 'price' => 0]);

        $module = Module::factory()->create(['formation_id' => $formation->id]);
        for ($i = 0; $i < $count; $i++) {
            Lesson::factory()->create([
                'module_id' => $module->id,
                'formation_id' => $formation->id,
            ]);
        }

        $enrollment = (new CreateEnrollmentAction())($customer, $formation);
        (new ValidateEnrollmentAction())($enrollment);

        return $enrollment->fresh();
    }
}
