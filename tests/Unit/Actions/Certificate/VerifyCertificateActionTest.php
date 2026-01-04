<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Certificate;

use App\Actions\Certificate\VerifyCertificateAction;
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
use Tests\TestCase;

final class VerifyCertificateActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_verifies_valid_certificate(): void
    {
        $certificate = $this->createCertificate();
        $action = app(VerifyCertificateAction::class);

        $result = $action($certificate->verification_code);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['reason']);
        $this->assertEquals($certificate->id, $result['certificate']->id);
    }

    #[Test]
    public function it_fails_for_non_existent_certificate(): void
    {
        $action = app(VerifyCertificateAction::class);

        $result = $action('INVALID');

        $this->assertFalse($result['valid']);
        $this->assertEquals('Certificate not found', $result['reason']);
        $this->assertNull($result['certificate']);
    }

    #[Test]
    public function it_fails_for_revoked_certificate(): void
    {
        $certificate = $this->createCertificate();
        $certificate->markAsRevoked('Test revocation');

        $action = app(VerifyCertificateAction::class);

        $result = $action($certificate->verification_code);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Certificate has been revoked', $result['reason']);
    }

    #[Test]
    public function it_fails_for_expired_certificate(): void
    {
        $certificate = $this->createCertificate();
        $certificate->update(['expires_at' => now()->subYear()]);
        $certificate->markAsExpired();

        $action = app(VerifyCertificateAction::class);

        $result = $action($certificate->verification_code);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Certificate has expired', $result['reason']);
    }

    #[Test]
    public function it_verifies_by_certificate_number(): void
    {
        $certificate = $this->createCertificate();
        $action = app(VerifyCertificateAction::class);

        $result = $action->byNumber($certificate->certificate_number);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['reason']);
        $this->assertEquals($certificate->id, $result['certificate']->id);
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

        $enrollment = (new CreateEnrollmentAction)($customer, $formation);
        (new ValidateEnrollmentAction)($enrollment);

        $lesson = $formation->lessons()->first();
        (new CompleteLessonAction)($enrollment, $lesson);

        return Certificate::factory()->active()->create([
            'enrollment_id' => $enrollment->fresh()->id,
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
        ]);
    }
}
