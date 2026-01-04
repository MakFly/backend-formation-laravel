<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Enrollment;

use App\Actions\Enrollment\CreateEnrollmentAction;
use App\Actions\Enrollment\ValidateEnrollmentAction;
use App\Enums\EnrollmentStatus;
use App\Enums\PricingTier;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class ValidateEnrollmentActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_validates_a_paid_enrollment(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create(['pricing_tier' => PricingTier::STANDARD, 'price' => 99.99]);

        $enrollment = (new CreateEnrollmentAction())($customer, $formation, [
            'amount_paid' => 99.99,
        ]);

        $action = new ValidateEnrollmentAction();
        $validated = $action($enrollment);

        $this->assertEquals(EnrollmentStatus::ACTIVE, $validated->status);
        $this->assertNotNull($validated->started_at);
    }

    #[Test]
    public function it_validates_a_free_formation(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create(['pricing_tier' => PricingTier::FREE, 'price' => 0]);

        $enrollment = (new CreateEnrollmentAction())($customer, $formation);

        $action = new ValidateEnrollmentAction();
        $validated = $action($enrollment);

        $this->assertEquals(EnrollmentStatus::ACTIVE, $validated->status);
    }

    #[Test]
    public function it_validates_a_free_tier_formation(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create(['pricing_tier' => PricingTier::FREE, 'price' => 0]);

        $enrollment = (new CreateEnrollmentAction())($customer, $formation);

        $action = new ValidateEnrollmentAction();
        $validated = $action($enrollment);

        $this->assertEquals(EnrollmentStatus::ACTIVE, $validated->status);
    }

    #[Test]
    public function it_fails_to_validate_unpaid_enrollment(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create(['pricing_tier' => PricingTier::STANDARD, 'price' => 99.99]);

        $enrollment = (new CreateEnrollmentAction())($customer, $formation, [
            'amount_paid' => 0,
        ]);

        $action = new ValidateEnrollmentAction();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payment required');

        $action($enrollment);
    }

    #[Test]
    public function it_only_validates_pending_enrollments(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();

        $enrollment = (new CreateEnrollmentAction())($customer, $formation);
        $enrollment->markAsActive();

        $action = new ValidateEnrollmentAction();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not in pending status');

        $action($enrollment);
    }

    #[Test]
    public function it_activates_already_started_enrollment(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create(['pricing_tier' => PricingTier::FREE, 'price' => 0]);

        $enrollment = (new CreateEnrollmentAction())($customer, $formation);
        $enrollment->started_at = now()->subDay();
        $enrollment->save();

        $action = new ValidateEnrollmentAction();
        $validated = $action($enrollment);

        $this->assertEquals(EnrollmentStatus::ACTIVE, $validated->status);
    }
}
