<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Admin;

use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Models\Customer;
use App\Models\Formation;
use App\Models\Payment;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_orders_with_pagination(): void
    {
        $controller = new AdminOrderController(
            $this->createMock(\App\Actions\Payment\RefundPaymentAction::class)
        );

        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();

        Payment::factory()->count(20)->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'type' => PaymentType::ENROLLMENT,
        ]);

        $request = Request::create('/api/v1/admin/orders', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data['data']['data']);
        $this->assertGreaterThanOrEqual(20, $data['data']['meta']['total']);
        $this->assertArrayHasKey('current_page', $data['data']['meta']);
        $this->assertArrayHasKey('per_page', $data['data']['meta']);
    }

    #[Test]
    public function it_filters_orders_by_status(): void
    {
        $controller = new AdminOrderController(
            $this->createMock(\App\Actions\Payment\RefundPaymentAction::class)
        );

        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();

        Payment::factory()->count(10)->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'status' => PaymentStatus::COMPLETED,
        ]);

        Payment::factory()->count(5)->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'status' => PaymentStatus::PENDING,
        ]);

        $request = Request::create('/api/v1/admin/orders?status=completed', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(10, $data['data']['meta']['total']);
    }

    #[Test]
    public function it_filters_orders_by_search(): void
    {
        $controller = new AdminOrderController(
            $this->createMock(\App\Actions\Payment\RefundPaymentAction::class)
        );

        $customer = Customer::factory()->create(['email' => 'test@example.com']);
        $formation = Formation::factory()->create();

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'stripe_payment_intent_id' => 'pi_test_12345',
        ]);

        Payment::factory()->create([
            'stripe_payment_intent_id' => 'pi_other_67890',
        ]);

        $request = Request::create('/api/v1/admin/orders?search=pi_test_12345', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(1, $data['data']['meta']['total']);
        $this->assertEquals('pi_test_12345', $data['data']['data'][0]['stripe_payment_intent_id']);
    }

    #[Test]
    public function it_filters_orders_by_amount_range(): void
    {
        $controller = new AdminOrderController(
            $this->createMock(\App\Actions\Payment\RefundPaymentAction::class)
        );

        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'amount' => 50,
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'amount' => 150,
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'amount' => 300,
        ]);

        $request = Request::create('/api/v1/admin/orders?amount_min=100&amount_max=200', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(1, $data['data']['meta']['total']);
        $this->assertEquals(150, $data['data']['data'][0]['amount']);
    }

    #[Test]
    public function it_shows_order_details(): void
    {
        $controller = new AdminOrderController(
            $this->createMock(\App\Actions\Payment\RefundPaymentAction::class)
        );

        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'status' => PaymentStatus::COMPLETED,
            'amount' => 199.99,
        ]);

        $request = Request::create("/api/v1/admin/orders/{$payment->id}", 'GET');

        $response = $controller->show($payment->id);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals($payment->id, $data['data']['id']);
        $this->assertEquals(199.99, $data['data']['amount']);
        $this->assertArrayHasKey('can_be_refunded', $data['data']);
        $this->assertArrayHasKey('refundable_amount', $data['data']);
    }

    #[Test]
    public function it_returns_order_statistics(): void
    {
        $controller = new AdminOrderController(
            $this->createMock(\App\Actions\Payment\RefundPaymentAction::class)
        );

        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();

        Payment::factory()->count(10)->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'status' => PaymentStatus::COMPLETED,
            'amount' => 100,
        ]);

        Payment::factory()->count(3)->create([
            'status' => PaymentStatus::PENDING,
        ]);

        Payment::factory()->count(2)->create([
            'status' => PaymentStatus::FAILED,
        ]);

        Payment::factory()->create([
            'status' => PaymentStatus::REFUNDED,
            'amount' => 100,
            'amount_refunded' => 100,
        ]);

        $request = Request::create('/api/v1/admin/orders/stats?period=30d', 'GET');

        $response = $controller->stats($request);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(16, $data['data']['summary']['total_orders']);
        $this->assertEquals(10, $data['data']['summary']['completed_orders']);
        $this->assertEquals(3, $data['data']['summary']['pending_orders']);
        $this->assertEquals(2, $data['data']['summary']['failed_orders']);
        $this->assertEquals(1, $data['data']['summary']['refunded_orders']);
        $this->assertEquals(1000, $data['data']['revenue']['total']);
        $this->assertEquals(100, $data['data']['revenue']['refunded']);
        $this->assertEquals(900, $data['data']['revenue']['net']);
        $this->assertEquals(100, $data['data']['revenue']['average_order_value']);
    }
}
