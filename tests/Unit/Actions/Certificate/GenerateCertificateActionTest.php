<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Certificate;

use App\Actions\Certificate\GenerateCertificateAction;
use App\Actions\Enrollment\CreateEnrollmentAction;
use App\Actions\Enrollment\ValidateEnrollmentAction;
use App\Actions\LessonProgress\CompleteLessonAction;
use App\Enums\PricingTier;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\Module;
use App\Support\Certificate\CertificatePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class GenerateCertificateActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create storage directory and fake PDF file
        $storagePath = storage_path('app/public/certificates');
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        file_put_contents($storagePath.'/test.pdf', 'fake pdf content');

        // Mock the CertificatePdfService to avoid actual file operations
        $this->partialMock(CertificatePdfService::class, function ($mock) use ($storagePath) {
            $fakePath = $storagePath.'/test.pdf';
            $mock->shouldReceive('generate')->andReturn($fakePath);
            $mock->shouldReceive('regenerate')->andReturn($fakePath);
        });
    }

    #[Test]
    public function it_generates_certificate_for_completed_enrollment(): void
    {
        $enrollment = $this->createCompletedEnrollment();
        $action = app(GenerateCertificateAction::class);

        $certificate = $action($enrollment);

        $this->assertNotNull($certificate);
        $this->assertEquals($enrollment->id, $certificate->enrollment_id);
        $this->assertEquals($enrollment->customer->id, $certificate->customer_id);
        $this->assertEquals($enrollment->formation->id, $certificate->formation_id);
        $this->assertNotNull($certificate->certificate_number);
        $this->assertNotNull($certificate->verification_code);
        $this->assertNotNull($certificate->issued_at);
    }

    #[Test]
    public function it_fails_for_incomplete_enrollment(): void
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

        $action = app(GenerateCertificateAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not completed');

        $action($enrollment);
    }

    #[Test]
    public function it_returns_existing_certificate_if_already_generated(): void
    {
        $enrollment = $this->createCompletedEnrollment();
        $action = app(GenerateCertificateAction::class);

        $certificate1 = $action($enrollment);
        $certificate2 = $action($enrollment);

        $this->assertEquals($certificate1->id, $certificate2->id);
    }

    #[Test]
    public function it_generates_unique_certificate_numbers(): void
    {
        $enrollment1 = $this->createCompletedEnrollment();
        $enrollment2 = $this->createCompletedEnrollment();
        $action = app(GenerateCertificateAction::class);

        $certificate1 = $action($enrollment1);
        $certificate2 = $action($enrollment2);

        $this->assertNotEquals($certificate1->certificate_number, $certificate2->certificate_number);
    }

    #[Test]
    public function it_generates_unique_verification_codes(): void
    {
        $enrollment1 = $this->createCompletedEnrollment();
        $enrollment2 = $this->createCompletedEnrollment();
        $action = app(GenerateCertificateAction::class);

        $certificate1 = $action($enrollment1);
        $certificate2 = $action($enrollment2);

        $this->assertNotEquals($certificate1->verification_code, $certificate2->verification_code);
    }

    private function createCompletedEnrollment(): Enrollment
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create([
            'pricing_tier' => PricingTier::FREE,
            'price' => 0,
            'instructor_name' => 'Test Instructor',
        ]);

        $module = Module::factory()->create(['formation_id' => $formation->id]);
        Lesson::factory()->create([
            'module_id' => $module->id,
            'formation_id' => $formation->id,
        ]);

        $enrollment = (new CreateEnrollmentAction)($customer, $formation);
        (new ValidateEnrollmentAction)($enrollment);

        // Complete the lesson from this formation
        $lesson = $formation->lessons()->first();
        (new CompleteLessonAction)($enrollment, $lesson);

        return $enrollment->fresh();
    }
}
