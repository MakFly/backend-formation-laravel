<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Payment;

use App\Actions\Payment\ProcessPaymentAction;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class ProcessPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_processes_successful_payment(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();

        $action = new ProcessPaymentAction;

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'type' => PaymentType::ENROLLMENT,
            'status' => PaymentStatus::PENDING,
            'amount' => 199.99,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $result = $action->__invoke('pi_test_123', 'card');

        $this->assertEquals($payment->id, $result->id);
        $this->assertEquals(PaymentStatus::COMPLETED, $result->status);
        $this->assertEquals('card', $result->payment_method_type);
        $this->assertNotNull($result->paid_at);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::COMPLETED->value,
            'payment_method_type' => 'card',
        ]);
    }

    #[Test]
    public function it_processes_payment_with_enrollment(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();
        $module = Module::factory()->create(['formation_id' => $formation->id]);
        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'formation_id' => $formation->id,
        ]);

        $action = new ProcessPaymentAction;

        $enrollment = Enrollment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'status' => EnrollmentStatus::PENDING,
            'amount_paid' => 0,
        ]);

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'enrollment_id' => $enrollment->id,
            'type' => PaymentType::ENROLLMENT,
            'status' => PaymentStatus::PENDING,
            'amount' => 199.99,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $result = $action->__invoke('pi_test_123', 'card');

        $enrollment->refresh();

        $this->assertEquals(PaymentStatus::COMPLETED, $result->status);
        $this->assertEquals(199.99, $enrollment->amount_paid);
    }

    #[Test]
    public function it_returns_existing_payment_if_already_completed(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();

        $action = new ProcessPaymentAction;

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'type' => PaymentType::ENROLLMENT,
            'status' => PaymentStatus::COMPLETED,
            'amount' => 199.99,
            'stripe_payment_intent_id' => 'pi_test_123',
            'payment_method_type' => 'card',
            'paid_at' => now()->subHour(),
        ]);

        $result = $action->__invoke('pi_test_123');

        $this->assertEquals($payment->id, $result->id);
        $this->assertEquals(PaymentStatus::COMPLETED, $result->status);
    }

    #[Test]
    public function it_throws_exception_if_payment_not_found(): void
    {
        $action = new ProcessPaymentAction;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment not found');

        $action->__invoke('pi_nonexistent');
    }

    #[Test]
    public function it_loads_relationships_when_processing_payment(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();

        $action = new ProcessPaymentAction;

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'type' => PaymentType::ENROLLMENT,
            'status' => PaymentStatus::PENDING,
            'amount' => 199.99,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $result = $action->__invoke('pi_test_123');

        $this->assertTrue($result->relationLoaded('customer'));
        $this->assertTrue($result->relationLoaded('enrollment'));
        $this->assertTrue($result->relationLoaded('formation'));
    }
}
