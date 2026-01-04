<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Admin;

use App\Http\Controllers\Api\Admin\ModuleController;
use App\Models\Formation;
use App\Models\Module;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModuleControllerTest extends TestCase
{
    use RefreshDatabase;

    private Formation $formation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formation = Formation::factory()->create([
            'is_published' => true,
        ]);
    }

    #[Test]
    public function it_lists_modules_for_formation(): void
    {
        $controller = new ModuleController();

        Module::factory()->count(3)->create(['formation_id' => $this->formation->id]);
        Module::factory()->count(2)->create(); // Other formation

        $request = Request::create("/api/v1/formations/{$this->formation->id}/modules", 'GET');

        $response = $controller->index($request, $this->formation->id);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data['data']);
        $this->assertCount(3, $data['data']);
    }

    #[Test]
    public function it_shows_module_details(): void
    {
        $controller = new ModuleController();

        $module = Module::factory()->create(['formation_id' => $this->formation->id]);
        Lesson::factory()->count(2)->create(['module_id' => $module->id, 'formation_id' => $this->formation->id]);

        $response = $controller->show($module->id);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals($module->id, $data['data']['id']);
        $this->assertEquals($module->title, $data['data']['title']);
        $this->assertEquals(2, $data['data']['lessons_count']);
        $this->assertArrayHasKey('total_duration', $data['data']);
        $this->assertArrayHasKey('published_lessons_count', $data['data']);
    }

    #[Test]
    public function it_creates_module(): void
    {
        $controller = new ModuleController();

        $request = Request::create("/api/v1/formations/{$this->formation->id}/modules", 'POST', [
            'title' => 'Introduction to Laravel',
            'description' => 'Learn the basics',
            'type' => 'video',
        ]);

        $response = $controller->store($request, $this->formation->id);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->status());
        $this->assertEquals('Introduction to Laravel', $data['data']['title']);
        $this->assertEquals('introduction-to-laravel', $data['data']['slug']);

        $this->assertDatabaseHas('modules', [
            'formation_id' => $this->formation->id,
            'title' => 'Introduction to Laravel',
        ]);
    }

    #[Test]
    public function it_auto_orders_new_modules(): void
    {
        $controller = new ModuleController();

        Module::factory()->create(['formation_id' => $this->formation->id, 'order' => 1]);
        Module::factory()->create(['formation_id' => $this->formation->id, 'order' => 2]);

        $request = Request::create("/api/v1/formations/{$this->formation->id}/modules", 'POST', [
            'title' => 'Third Module',
        ]);

        $controller->store($request, $this->formation->id);

        $this->assertDatabaseHas('modules', [
            'formation_id' => $this->formation->id,
            'title' => 'Third Module',
            'order' => 3,
        ]);
    }

    #[Test]
    public function it_updates_module(): void
    {
        $controller = new ModuleController();

        $module = Module::factory()->create(['formation_id' => $this->formation->id]);

        $request = Request::create("/api/v1/formations/modules/{$module->id}", 'PATCH', [
            'title' => 'Updated Title',
            'is_published' => true,
        ]);

        $response = $controller->update($request, $module->id);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Updated Title', $data['data']['title']);
        $this->assertTrue($data['data']['is_published']);
    }

    #[Test]
    public function it_deletes_module(): void
    {
        $controller = new ModuleController();

        $module = Module::factory()->create(['formation_id' => $this->formation->id]);

        $response = $controller->destroy($module->id);

        $this->assertEquals(204, $response->status());
        $this->assertSoftDeleted('modules', ['id' => $module->id]);
    }

    #[Test]
    public function it_prevents_deleting_module_with_lessons(): void
    {
        $controller = new ModuleController();

        $module = Module::factory()->create(['formation_id' => $this->formation->id]);
        Lesson::factory()->create(['module_id' => $module->id, 'formation_id' => $this->formation->id]);

        $response = $controller->destroy($module->id);

        $this->assertEquals(400, $response->status());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Cannot delete module with lessons', $data['message']);
    }

    #[Test]
    public function it_reorders_modules(): void
    {
        $controller = new ModuleController();

        $module1 = Module::factory()->create(['formation_id' => $this->formation->id, 'order' => 1]);
        $module2 = Module::factory()->create(['formation_id' => $this->formation->id, 'order' => 2]);
        $module3 = Module::factory()->create(['formation_id' => $this->formation->id, 'order' => 3]);

        // Swap module1 and module3
        $request = Request::create("/api/v1/formations/{$this->formation->id}/modules/reorder", 'POST', [
            'modules' => [
                ['id' => $module3->id, 'order' => 1],
                ['id' => $module2->id, 'order' => 2],
                ['id' => $module1->id, 'order' => 3],
            ],
        ]);

        $response = $controller->reorder($request, $this->formation->id);

        $this->assertEquals(200, $response->status());

        $this->assertDatabaseHas('modules', ['id' => $module3->id, 'order' => 1]);
        $this->assertDatabaseHas('modules', ['id' => $module2->id, 'order' => 2]);
        $this->assertDatabaseHas('modules', ['id' => $module1->id, 'order' => 3]);
    }

    #[Test]
    public function it_publishes_module(): void
    {
        $controller = new ModuleController();

        $module = Module::factory()->create([
            'formation_id' => $this->formation->id,
            'is_published' => false,
        ]);

        $response = $controller->publish($module->id);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['is_published']);
        $this->assertNotNull($data['data']['published_at']);
    }

    #[Test]
    public function it_unpublishes_module(): void
    {
        $controller = new ModuleController();

        $module = Module::factory()->create([
            'formation_id' => $this->formation->id,
            'is_published' => true,
        ]);

        $response = $controller->unpublish($module->id);

        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['data']['is_published']);
    }

    #[Test]
    public function it_returns_module_lessons(): void
    {
        $controller = new ModuleController();

        $module = Module::factory()->create(['formation_id' => $this->formation->id]);
        Lesson::factory()->count(3)->create(['module_id' => $module->id, 'formation_id' => $this->formation->id]);

        $response = $controller->lessons($module->id);

        $data = json_decode($response->getContent(), true);

        $this->assertCount(3, $data['data']);
    }
}
