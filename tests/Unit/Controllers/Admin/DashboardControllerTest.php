<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private Formation $formation;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formation = Formation::factory()->create([
            'price' => 199.99,
            'is_published' => true,
        ]);

        $this->customer = Customer::factory()->create();
    }

    #[Test]
    public function it_returns_dashboard_statistics(): void
    {
        $controller = new DashboardController;

        // Create some test data
        Payment::factory()->count(5)->completed()->create([
            'amount' => 100,
            'formation_id' => $this->formation->id,
            'customer_id' => $this->customer->id,
            'paid_at' => now()->subDays(5),
        ]);

        Payment::factory()->create([
            'status' => PaymentStatus::REFUNDED,
            'amount' => 50,
            'amount_refunded' => 50,
            'formation_id' => $this->formation->id,
            'customer_id' => $this->customer->id,
            'refunded_at' => now()->subDays(2),
        ]);

        Enrollment::factory()->count(3)->create([
            'formation_id' => $this->formation->id,
            'customer_id' => $this->customer->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        Enrollment::factory()->create([
            'formation_id' => $this->formation->id,
            'customer_id' => $this->customer->id,
            'status' => EnrollmentStatus::COMPLETED,
        ]);

        $request = Request::create('/api/v1/admin/dashboard', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(500, $data['data']['revenue']['total']); // 5 * 100
        $this->assertEquals(50, $data['data']['revenue']['refunded']);
        $this->assertEquals(450, $data['data']['revenue']['net']);
        $this->assertEquals(5, $data['data']['payments']['completed']);
        $this->assertEquals(4, $data['data']['enrollments']['total']); // 3 active + 1 completed
        $this->assertEquals(3, $data['data']['enrollments']['active']);
        $this->assertEquals(1, $data['data']['enrollments']['completed']);
        $this->assertIsArray($data['data']['revenue']['by_month']);
        $this->assertIsArray($data['data']['popular_formations']);
        $this->assertIsArray($data['data']['recent_payments']);
        $this->assertArrayHasKey('conversion_rate', $data['data']['metrics']);
        $this->assertArrayHasKey('average_order_value', $data['data']['metrics']);
        $this->assertArrayHasKey('completion_rate', $data['data']['metrics']);
    }

    #[Test]
    public function it_filters_by_period(): void
    {
        $controller = new DashboardController;

        // Old payment (outside 30d window)
        Payment::factory()->create([
            'status' => PaymentStatus::COMPLETED,
            'amount' => 100,
            'paid_at' => now()->subDays(60),
        ]);

        // Recent payment (inside 30d window)
        Payment::factory()->create([
            'status' => PaymentStatus::COMPLETED,
            'amount' => 200,
            'paid_at' => now()->subDays(10),
        ]);

        $request = Request::create('/api/v1/admin/dashboard?period=30d', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        // Should only include recent payment
        $this->assertEquals(200, $data['data']['revenue']['total']);
    }

    #[Test]
    public function it_returns_revenue_analytics(): void
    {
        $controller = new DashboardController;

        Payment::factory()->count(3)->create([
            'status' => PaymentStatus::COMPLETED,
            'amount' => 100,
            'paid_at' => now()->subDays(5),
        ]);

        $request = Request::create('/api/v1/admin/dashboard/revenue?period=30d&group_by=day', 'GET');

        $response = $controller->revenue($request);

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function it_returns_popular_formations(): void
    {
        $controller = new DashboardController;

        // Create multiple formations with different enrollment counts
        $formation1 = Formation::factory()->create(['is_published' => true]);
        $formation2 = Formation::factory()->create(['is_published' => true]);

        Enrollment::factory()->count(10)->create(['formation_id' => $formation1->id]);
        Enrollment::factory()->count(5)->create(['formation_id' => $formation2->id]);

        $request = Request::create('/api/v1/admin/dashboard/popular-formations?limit=5', 'GET');

        $response = $controller->popularFormations($request);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data['data']);
        $this->assertGreaterThanOrEqual(2, count($data['data']));
        // formation1 should come first with more enrollments
        $this->assertEquals($formation1->id, $data['data'][0]['id']);
    }
}
