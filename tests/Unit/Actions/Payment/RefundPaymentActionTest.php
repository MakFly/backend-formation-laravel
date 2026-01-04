<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Payment;

use App\Actions\Payment\RefundPaymentAction;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Formation;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class RefundPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_refunds_payment_fully(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'type' => PaymentType::ENROLLMENT,
            'status' => PaymentStatus::COMPLETED,
            'amount' => 199.99,
            'amount_refunded' => 0,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $stripeRefund = new \Stripe\Refund('re_test_123');
        $stripeRefund->amount = 19999; // in cents
        $stripeRefund->currency = 'eur';
        $stripeRefund->status = 'succeeded';
        $stripeRefund->created = time();

        $stripeService = \Mockery::mock(\App\Support\Stripe\StripePaymentService::class);
        $stripeService->shouldReceive('createRefund')
            ->once()
            ->with($payment, 199.99, null)
            ->andReturn($stripeRefund);

        $action = new RefundPaymentAction($stripeService);

        $result = $action->__invoke($payment);

        $this->assertEquals(PaymentStatus::REFUNDED, $result['payment']->status);
        $this->assertEquals(199.99, $result['payment']->amount_refunded);
        $this->assertNotNull($result['payment']->refunded_at);
        $this->assertEquals('re_test_123', $result['stripe_refund']->id);
    }

    #[Test]
    public function it_refunds_payment_partially(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();

        $stripeRefund = new \Stripe\Refund('re_test_123');
        $stripeRefund->amount = 9999; // in cents
        $stripeRefund->currency = 'eur';
        $stripeRefund->status = 'succeeded';
        $stripeRefund->created = time();

        $stripeService = \Mockery::mock(\App\Support\Stripe\StripePaymentService::class);
        $stripeService->shouldReceive('createRefund')
            ->once()
            ->with(\Mockery::type(Payment::class), 99.99, null)
            ->andReturn($stripeRefund);

        $action = new RefundPaymentAction($stripeService);

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'type' => PaymentType::ENROLLMENT,
            'status' => PaymentStatus::COMPLETED,
            'amount' => 199.99,
            'amount_refunded' => 0,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $result = $action->__invoke($payment, 99.99);

        $this->assertEquals(PaymentStatus::PARTIALLY_REFUNDED, $result['payment']->status);
        $this->assertEquals(99.99, $result['payment']->amount_refunded);
    }

    #[Test]
    public function it_refunds_payment_with_reason(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();

        $stripeRefund = new \Stripe\Refund('re_test_123');
        $stripeRefund->amount = 19999;
        $stripeRefund->currency = 'eur';
        $stripeRefund->status = 'succeeded';
        $stripeRefund->created = time();

        $stripeService = \Mockery::mock(\App\Support\Stripe\StripePaymentService::class);
        $stripeService->shouldReceive('createRefund')
            ->once()
            ->with(\Mockery::type(Payment::class), 199.99, 'requested_by_customer')
            ->andReturn($stripeRefund);

        $action = new RefundPaymentAction($stripeService);

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'type' => PaymentType::ENROLLMENT,
            'status' => PaymentStatus::COMPLETED,
            'amount' => 199.99,
            'amount_refunded' => 0,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $result = $action->__invoke($payment, 199.99, 'requested_by_customer');

        $this->assertEquals(PaymentStatus::REFUNDED, $result['payment']->status);
    }

    #[Test]
    public function it_throws_exception_if_payment_cannot_be_refunded(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();

        $stripeService = \Mockery::mock(\App\Support\Stripe\StripePaymentService::class);
        $action = new RefundPaymentAction($stripeService);

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'type' => PaymentType::ENROLLMENT,
            'status' => PaymentStatus::PENDING,
            'amount' => 199.99,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment cannot be refunded');

        $action->__invoke($payment);
    }

    #[Test]
    public function it_loads_relationships_when_refunding_payment(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();

        $stripeRefund = new \Stripe\Refund('re_test_123');
        $stripeRefund->amount = 19999;
        $stripeRefund->currency = 'eur';
        $stripeRefund->status = 'succeeded';
        $stripeRefund->created = time();

        $stripeService = \Mockery::mock(\App\Support\Stripe\StripePaymentService::class);
        $stripeService->shouldReceive('createRefund')
            ->once()
            ->andReturn($stripeRefund);

        $action = new RefundPaymentAction($stripeService);

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'type' => PaymentType::ENROLLMENT,
            'status' => PaymentStatus::COMPLETED,
            'amount' => 199.99,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $result = $action->__invoke($payment);

        $this->assertTrue($result['payment']->relationLoaded('customer'));
        $this->assertTrue($result['payment']->relationLoaded('enrollment'));
        $this->assertTrue($result['payment']->relationLoaded('formation'));
    }

    #[Test]
    public function it_refunds_remaining_amount_if_already_partially_refunded(): void
    {
        $formation = Formation::factory()->create(['price' => 199.99]);
        $customer = Customer::factory()->create();

        $stripeRefund = new \Stripe\Refund('re_test_123');
        $stripeRefund->amount = 5000;
        $stripeRefund->currency = 'eur';
        $stripeRefund->status = 'succeeded';
        $stripeRefund->created = time();

        $stripeService = \Mockery::mock(\App\Support\Stripe\StripePaymentService::class);
        $stripeService->shouldReceive('createRefund')
            ->once()
            ->andReturn($stripeRefund);

        $action = new RefundPaymentAction($stripeService);

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'type' => PaymentType::ENROLLMENT,
            'status' => PaymentStatus::PARTIALLY_REFUNDED,
            'amount' => 199.99,
            'amount_refunded' => 149.99,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $result = $action->__invoke($payment);

        $this->assertEquals(PaymentStatus::REFUNDED, $result['payment']->status);
        $this->assertEquals(199.99, $result['payment']->amount_refunded);
    }
}
