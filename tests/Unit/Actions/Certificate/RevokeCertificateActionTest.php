<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Certificate;

use App\Actions\Certificate\RevokeCertificateAction;
use App\Actions\Enrollment\CreateEnrollmentAction;
use App\Actions\Enrollment\ValidateEnrollmentAction;
use App\Actions\LessonProgress\CompleteLessonAction;
use App\Enums\PricingTier;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class RevokeCertificateActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_revokes_certificate(): void
    {
        $certificate = $this->createCertificate();
        $action = app(RevokeCertificateAction::class);

        $revoked = $action($certificate, 'Test revocation');

        $this->assertTrue($revoked->isRevoked());
        $this->assertEquals('Test revocation', $revoked->revoked_reason);
        $this->assertNotNull($revoked->revoked_at);
    }

    #[Test]
    public function it_fails_to_revoke_already_revoked_certificate(): void
    {
        $certificate = $this->createCertificate();
        $certificate->markAsRevoked('Already revoked');

        $action = app(RevokeCertificateAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already revoked');

        $action($certificate);
    }

    #[Test]
    public function it_revokes_without_reason(): void
    {
        $certificate = $this->createCertificate();
        $action = app(RevokeCertificateAction::class);

        $revoked = $action($certificate);

        $this->assertTrue($revoked->isRevoked());
        $this->assertNull($revoked->revoked_reason);
    }

    private function createCertificate(): Certificate
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

        $lesson = $formation->lessons()->first();
        (new CompleteLessonAction())($enrollment, $lesson);

        return Certificate::factory()->active()->create([
            'enrollment_id' => $enrollment->fresh()->id,
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
        ]);
    }
}
