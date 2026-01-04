<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Payment;

use App\Actions\Payment\CreatePaymentAction;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Formation;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreatePaymentActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_payment_for_enrollment(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();

        // Mock Stripe service to avoid actual API call
        $stripeService = \Mockery::mock(\App\Support\Stripe\StripePaymentService::class);
        $stripeService->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn('https://checkout.stripe.com/pay/cs_test_123');

        $action = new CreatePaymentAction($stripeService);

        $result = $action->forEnrollment($customer, $formation);

        $this->assertInstanceOf(Payment::class, $result['payment']);
        $this->assertEquals($customer->id, $result['payment']->customer_id);
        $this->assertEquals($formation->id, $result['payment']->formation_id);
        $this->assertEquals(PaymentType::ENROLLMENT, $result['payment']->type);
        $this->assertEquals(PaymentStatus::PENDING, $result['payment']->status);
        $this->assertEquals(199.99, $result['payment']->amount);
        $this->assertEquals('EUR', $result['payment']->currency);
        $this->assertIsString($result['checkout_url']);
        $this->assertStringStartsWith('https://checkout.stripe.com/', $result['checkout_url']);
    }

    #[Test]
    public function it_creates_payment_with_existing_enrollment(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();

        $enrollment = \App\Models\Enrollment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
        ]);

        $stripeService = \Mockery::mock(\App\Support\Stripe\StripePaymentService::class);
        $stripeService->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn('https://checkout.stripe.com/pay/cs_test_123');

        $action = new CreatePaymentAction($stripeService);

        $result = $action->forEnrollment($customer, $formation, $enrollment);

        $this->assertEquals($enrollment->id, $result['payment']->enrollment_id);
    }

    #[Test]
    public function it_creates_payment_with_correct_description(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99, 'title' => 'Test Formation']);
        $customer = Customer::factory()->create();

        $stripeService = \Mockery::mock(\App\Support\Stripe\StripePaymentService::class);
        $stripeService->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn('https://checkout.stripe.com/pay/cs_test_123');

        $action = new CreatePaymentAction($stripeService);

        $result = $action->forEnrollment($customer, $formation);

        $this->assertEquals('Enrollment: Test Formation', $result['payment']->description);
    }

    #[Test]
    public function it_creates_payment_directly_with_array_data(): void
    {
        $formation = Formation::factory()->create();
        $customer = Customer::factory()->create();

        // The direct() method doesn't use Stripe service, so we don't need to mock it
        // But the constructor requires it, so we pass a mock
        $stripeService = \Mockery::mock(\App\Support\Stripe\StripePaymentService::class);
        $action = new CreatePaymentAction($stripeService);

        $data = [
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'type' => 'enrollment',
            'amount' => 299.99,
            'currency' => 'EUR',
            'description' => 'Test payment',
        ];

        $payment = $action->direct($data);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals($customer->id, $payment->customer_id);
        $this->assertEquals($formation->id, $payment->formation_id);
        $this->assertEquals(299.99, $payment->amount);
        $this->assertEquals('Test payment', $payment->description);
    }
}
