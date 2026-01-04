<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Enrollment;

use App\Actions\Enrollment\CreateEnrollmentAction;
use App\Enums\EnrollmentStatus;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class CreateEnrollmentActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_an_enrollment(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();
        $action = new CreateEnrollmentAction();

        $enrollment = $action($customer, $formation, [
            'amount_paid' => 99.99,
            'payment_reference' => 'pay_12345',
        ]);

        $this->assertInstanceOf(Enrollment::class, $enrollment);
        $this->assertEquals($customer->id, $enrollment->customer_id);
        $this->assertEquals($formation->id, $enrollment->formation_id);
        $this->assertEquals(EnrollmentStatus::PENDING, $enrollment->status);
        $this->assertEquals(99.99, $enrollment->amount_paid);
        $this->assertEquals('pay_12345', $enrollment->payment_reference);
        $this->assertNotNull($enrollment->enrolled_at);
    }

    #[Test]
    public function it_increments_formation_enrollment_count(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create(['enrollment_count' => 5]);
        $action = new CreateEnrollmentAction();

        $action($customer, $formation);

        $this->assertEquals(6, $formation->fresh()->enrollment_count);
    }

    #[Test]
    public function it_prevents_duplicate_enrollment(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();
        $action = new CreateEnrollmentAction();

        // First enrollment
        $action($customer, $formation);

        // Try to enroll again
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already enrolled');

        $action($customer, $formation);
    }

    #[Test]
    public function it_allows_enrollment_after_cancellation(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();
        $action = new CreateEnrollmentAction();

        // First enrollment
        $enrollment = $action($customer, $formation);
        $enrollment->markAsCancelled();

        // Should allow new enrollment after cancellation
        $newEnrollment = $action($customer, $formation);

        $this->assertNotEquals($enrollment->id, $newEnrollment->id);
    }

    #[Test]
    public function it_creates_enrollment_with_default_values(): void
    {
        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();
        $action = new CreateEnrollmentAction();

        $enrollment = $action($customer, $formation);

        $this->assertEquals(0, $enrollment->amount_paid);
        $this->assertNull($enrollment->payment_reference);
        $this->assertEquals(0, $enrollment->progress_percentage);
        $this->assertEquals(0, $enrollment->access_count);
    }
}
