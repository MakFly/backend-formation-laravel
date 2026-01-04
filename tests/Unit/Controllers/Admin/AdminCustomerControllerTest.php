<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Api\Admin\AdminCustomerController;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminCustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_customers_with_pagination(): void
    {
        $controller = new AdminCustomerController;

        Customer::factory()->count(25)->create();

        $request = Request::create('/api/v1/admin/customers', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data['data']['data']);
        $this->assertGreaterThanOrEqual(25, $data['data']['meta']['total']);
        $this->assertArrayHasKey('current_page', $data['data']['meta']);
        $this->assertArrayHasKey('per_page', $data['data']['meta']);
        $this->assertArrayHasKey('last_page', $data['data']['meta']);
    }

    #[Test]
    public function it_filters_customers_by_search(): void
    {
        $controller = new AdminCustomerController;

        Customer::factory()->create([
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        Customer::factory()->create([
            'email' => 'jane.smith@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $request = Request::create('/api/v1/admin/customers?search=john', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(1, $data['data']['meta']['total']);
        $this->assertEquals('john.doe@example.com', $data['data']['data'][0]['email']);
    }

    #[Test]
    public function it_includes_customer_stats_when_requested(): void
    {
        $controller = new AdminCustomerController;

        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();

        // Create enrollments and payments
        Enrollment::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'status' => PaymentStatus::COMPLETED,
            'amount' => 100,
        ]);

        $request = Request::create('/api/v1/admin/customers?include_stats=1', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        // Find the customer in the response
        $foundCustomer = collect($data['data']['data'])->first(fn ($c) => $c['id'] === $customer->id);

        $this->assertNotNull($foundCustomer);
        $this->assertEquals(3, $foundCustomer['enrollments_count']);
        $this->assertEquals(100, $foundCustomer['total_spent']);
    }

    #[Test]
    public function it_shows_customer_details(): void
    {
        $controller = new AdminCustomerController;

        $customer = Customer::factory()->create();

        $request = Request::create("/api/v1/admin/customers/{$customer->id}", 'GET');

        $response = $controller->show($customer->id);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals($customer->id, $data['data']['id']);
        $this->assertEquals($customer->email, $data['data']['email']);
        $this->assertArrayHasKey('enrollments_count', $data['data']);
        $this->assertArrayHasKey('total_spent', $data['data']);
    }

    #[Test]
    public function it_returns_customer_enrollments(): void
    {
        $controller = new AdminCustomerController;

        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();

        Enrollment::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
        ]);

        $request = Request::create("/api/v1/admin/customers/{$customer->id}/enrollments", 'GET');

        $response = $controller->enrollments($customer->id, $request);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data['data']);
        $this->assertEquals(3, $data['data']['meta']['total']);
    }

    #[Test]
    public function it_returns_customer_payments(): void
    {
        $controller = new AdminCustomerController;

        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();

        Payment::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
        ]);

        $request = Request::create("/api/v1/admin/customers/{$customer->id}/payments", 'GET');

        $response = $controller->payments($customer->id, $request);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data['data']);
        $this->assertEquals(3, $data['data']['meta']['total']);
    }

    #[Test]
    public function it_returns_customer_statistics(): void
    {
        $controller = new AdminCustomerController;

        $customer = Customer::factory()->create();
        $formation = Formation::factory()->create();

        Enrollment::factory()->count(5)->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'status' => \App\Enums\EnrollmentStatus::ACTIVE,
        ]);

        Enrollment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'status' => \App\Enums\EnrollmentStatus::COMPLETED,
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'status' => PaymentStatus::COMPLETED,
            'amount' => 500,
        ]);

        $request = Request::create("/api/v1/admin/customers/{$customer->id}/stats", 'GET');

        $response = $controller->stats($customer->id);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(6, $data['data']['enrollments']['total']);
        $this->assertEquals(5, $data['data']['enrollments']['active']);
        $this->assertEquals(1, $data['data']['enrollments']['completed']);
        $this->assertEquals(500, $data['data']['payments']['total_spent']);
    }
}
