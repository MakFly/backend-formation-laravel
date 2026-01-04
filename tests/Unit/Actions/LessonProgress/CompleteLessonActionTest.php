<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\LessonProgress;

use App\Actions\Enrollment\CreateEnrollmentAction;
use App\Actions\Enrollment\ValidateEnrollmentAction;
use App\Actions\LessonProgress\CompleteLessonAction;
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

final class CompleteLessonActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_marks_lesson_as_completed(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();
        $lesson = Lesson::first();
        $action = new CompleteLessonAction;

        $progress = $action($enrollment, $lesson);

        $this->assertEquals(LessonProgressStatus::COMPLETED, $progress->status);
        $this->assertEquals(100, $progress->progress_percentage);
        $this->assertNotNull($progress->completed_at);
    }

    #[Test]
    public function it_updates_completion_timestamp(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();
        $lesson = Lesson::first();

        // Start lesson first
        $progress = $enrollment->lessonProgress()->create([
            'lesson_id' => $lesson->id,
            'status' => LessonProgressStatus::IN_PROGRESS,
            'started_at' => now()->subHour(),
        ]);

        $startedAt = $progress->started_at;

        $action = new CompleteLessonAction;
        $action($enrollment, $lesson);

        $completedAt = $progress->fresh()->completed_at;
        $this->assertNotNull($completedAt);
        $this->assertGreaterThan($startedAt, $completedAt);
    }

    #[Test]
    public function it_idempotently_completes_lesson(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();
        $lesson = Lesson::first();
        $action = new CompleteLessonAction;

        // Complete once
        $progress1 = $action($enrollment, $lesson);
        $firstCompletedAt = $progress1->completed_at;

        // Complete again (idempotent)
        $progress2 = $action($enrollment, $lesson);
        $secondCompletedAt = $progress2->completed_at;

        $this->assertEquals($firstCompletedAt->toIso8601String(), $secondCompletedAt->toIso8601String());
    }

    #[Test]
    public function it_refreshes_enrollment_progress_on_completion(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create(['pricing_tier' => PricingTier::FREE, 'price' => 0]);

        // Create 2 lessons
        $module = Module::factory()->create(['formation_id' => $formation->id]);
        Lesson::factory()->create([
            'module_id' => $module->id,
            'formation_id' => $formation->id,
        ]);
        Lesson::factory()->create([
            'module_id' => $module->id,
            'formation_id' => $formation->id,
        ]);

        $enrollment = (new CreateEnrollmentAction)($customer, $formation);
        (new ValidateEnrollmentAction)($enrollment);

        // Complete one lesson
        $lesson1 = $formation->lessons()->first();
        $action = new CompleteLessonAction;
        $action($enrollment, $lesson1);

        // Enrollment progress should be 50%
        $this->assertEquals(50, $enrollment->fresh()->progress_percentage);
    }

    #[Test]
    public function it_marks_enrollment_as_completed_when_all_lessons_done(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create(['pricing_tier' => PricingTier::FREE, 'price' => 0]);

        // Create single lesson
        $module = Module::factory()->create(['formation_id' => $formation->id]);
        Lesson::factory()->create([
            'module_id' => $module->id,
            'formation_id' => $formation->id,
        ]);

        $enrollment = (new CreateEnrollmentAction)($customer, $formation);
        (new ValidateEnrollmentAction)($enrollment);

        $lesson = $formation->lessons()->first();
        $action = new CompleteLessonAction;
        $action($enrollment, $lesson);

        // Enrollment should be 100% complete
        $this->assertEquals(100, $enrollment->fresh()->progress_percentage);
        $this->assertTrue($enrollment->fresh()->isCompleted());
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

        $enrollment = (new CreateEnrollmentAction)($customer, $formation);
        (new ValidateEnrollmentAction)($enrollment);

        return $enrollment->fresh();
    }
}
