<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Admin;

use App\Http\Controllers\Api\Admin\AdminFormationController;
use App\Models\Formation;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminFormationControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_formations_with_pagination(): void
    {
        $controller = new AdminFormationController();

        Formation::factory()->count(15)->create(['is_published' => true]);
        Formation::factory()->count(5)->create(['is_published' => false]);

        $request = Request::create('/api/v1/admin/formations', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data['data']['data']);
        $this->assertGreaterThanOrEqual(20, $data['data']['meta']['total']);
        $this->assertArrayHasKey('current_page', $data['data']['meta']);
        $this->assertArrayHasKey('per_page', $data['data']['meta']);
    }

    #[Test]
    public function it_filters_formations_by_published_status(): void
    {
        $controller = new AdminFormationController();

        Formation::factory()->count(10)->create(['is_published' => true]);
        Formation::factory()->count(5)->create(['is_published' => false]);

        $request = Request::create('/api/v1/admin/formations?is_published=1', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(10, $data['data']['meta']['total']);
    }

    #[Test]
    public function it_filters_formations_by_search(): void
    {
        $controller = new AdminFormationController();

        Formation::factory()->create([
            'title' => 'Laravel for Beginners',
            'is_published' => true,
        ]);

        Formation::factory()->create([
            'title' => 'Advanced Python',
            'is_published' => true,
        ]);

        $request = Request::create('/api/v1/admin/formations?search=Laravel', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(1, $data['data']['meta']['total']);
        $this->assertStringContainsString('Laravel', $data['data']['data'][0]['title']);
    }

    #[Test]
    public function it_includes_formation_stats_when_requested(): void
    {
        $controller = new AdminFormationController();

        $formation = Formation::factory()->create(['is_published' => true]);
        $customer = Customer::factory()->create();

        Enrollment::factory()->count(5)->create([
            'formation_id' => $formation->id,
            'customer_id' => $customer->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        Payment::factory()->create([
            'formation_id' => $formation->id,
            'customer_id' => $customer->id,
            'status' => PaymentStatus::COMPLETED,
            'amount' => 199.99,
        ]);

        $request = Request::create('/api/v1/admin/formations?include_stats=1', 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $formationData = collect($data['data']['data'])->first(fn ($f) => $f['id'] === $formation->id);

        $this->assertNotNull($formationData);
        $this->assertEquals(5, $formationData['enrollments_count']);
        $this->assertEquals(199.99, $formationData['revenue']);
    }

    #[Test]
    public function it_shows_formation_details(): void
    {
        $controller = new AdminFormationController();

        $formation = Formation::factory()->create(['is_published' => true]);

        $request = Request::create("/api/v1/admin/formations/{$formation->id}", 'GET');

        $response = $controller->show($formation->id);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals($formation->id, $data['data']['id']);
        $this->assertEquals($formation->title, $data['data']['title']);
        $this->assertArrayHasKey('enrollments_count', $data['data']);
        $this->assertArrayHasKey('revenue', $data['data']);
    }

    #[Test]
    public function it_returns_formation_statistics(): void
    {
        $controller = new AdminFormationController();

        $formation = Formation::factory()->create(['is_published' => true]);
        $customer = Customer::factory()->create();

        Enrollment::factory()->count(10)->create([
            'formation_id' => $formation->id,
            'customer_id' => $customer->id,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        Enrollment::factory()->create([
            'formation_id' => $formation->id,
            'customer_id' => $customer->id,
            'status' => EnrollmentStatus::COMPLETED,
        ]);

        Payment::factory()->create([
            'formation_id' => $formation->id,
            'customer_id' => $customer->id,
            'status' => PaymentStatus::COMPLETED,
            'amount' => 1000,
        ]);

        Payment::factory()->create([
            'formation_id' => $formation->id,
            'customer_id' => $customer->id,
            'status' => PaymentStatus::REFUNDED,
            'amount' => 100,
            'amount_refunded' => 100,
        ]);

        $request = Request::create("/api/v1/admin/formations/{$formation->id}/stats", 'GET');

        $response = $controller->stats($formation->id);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(11, $data['data']['enrollments']['total']);
        $this->assertEquals(10, $data['data']['enrollments']['active']);
        $this->assertEquals(1, $data['data']['enrollments']['completed']);
        $this->assertEquals(1000, $data['data']['revenue']['total']);
        $this->assertEquals(100, $data['data']['revenue']['refunded']);
        $this->assertEquals(900, $data['data']['revenue']['net']);
    }
}
