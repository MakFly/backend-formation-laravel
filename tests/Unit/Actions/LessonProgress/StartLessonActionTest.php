<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\LessonProgress;

use App\Actions\Enrollment\CreateEnrollmentAction;
use App\Actions\Enrollment\ValidateEnrollmentAction;
use App\Actions\LessonProgress\StartLessonAction;
use App\Enums\LessonProgressStatus;
use App\Enums\PricingTier;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class StartLessonActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_starts_a_lesson(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();
        $lesson = Lesson::first();
        $action = new StartLessonAction();

        $progress = $action($enrollment, $lesson);

        $this->assertEquals($enrollment->id, $progress->enrollment_id);
        $this->assertEquals($lesson->id, $progress->lesson_id);
        $this->assertEquals(LessonProgressStatus::IN_PROGRESS, $progress->status);
        $this->assertNotNull($progress->started_at);
        $this->assertNotNull($progress->last_accessed_at);
        $this->assertEquals(1, $progress->access_count);
    }

    #[Test]
    public function it_records_start_position(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();
        $lesson = Lesson::first();
        $action = new StartLessonAction();

        $progress = $action($enrollment, $lesson, 120);

        $this->assertEquals(120, $progress->current_position);
    }

    #[Test]
    public function it_updates_existing_progress(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();
        $lesson = Lesson::first();
        $action = new StartLessonAction();

        // First start
        $progress = $action($enrollment, $lesson);
        $this->assertEquals(1, $progress->access_count);

        // Second start
        $progress = $action($enrollment, $lesson);
        $this->assertEquals(2, $progress->access_count);
    }

    #[Test]
    public function it_fails_for_lesson_from_different_formation(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();

        // Create a lesson from a different formation
        $otherFormation = Formation::factory()->create();
        $module = Module::factory()->create(['formation_id' => $otherFormation->id]);
        $otherLesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'formation_id' => $otherFormation->id,
        ]);

        $action = new StartLessonAction();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not belong to the enrolled formation');

        $action($enrollment, $otherLesson);
    }

    #[Test]
    public function it_updates_enrollment_last_accessed(): void
    {
        $enrollment = $this->createEnrollmentWithLesson();
        $lesson = Lesson::first();

        $enrollment->last_accessed_at = now()->subDay();
        $enrollment->save();

        $action = new StartLessonAction();
        $action($enrollment, $lesson);

        $this->assertGreaterThan(now()->subMinute(), $enrollment->fresh()->last_accessed_at);
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
}
