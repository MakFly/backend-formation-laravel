<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Admin;

use App\Http\Controllers\Api\Admin\LessonController;
use App\Models\Formation;
use App\Models\Module;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LessonControllerTest extends TestCase
{
    use RefreshDatabase;

    private Formation $formation;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formation = Formation::factory()->create(['is_published' => true]);
        $this->module = Module::factory()->create(['formation_id' => $this->formation->id]);
    }

    #[Test]
    public function it_lists_lessons_for_module(): void
    {
        $controller = new LessonController();

        Lesson::factory()->count(3)->create([
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
        ]);
        Lesson::factory()->count(2)->create(['formation_id' => $this->formation->id]);

        $request = Request::create("/api/v1/lessons?module_id={$this->module->id}", 'GET');

        $response = $controller->index($request, $this->module->id);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data['data']);
        $this->assertCount(3, $data['data']);
    }

    #[Test]
    public function it_lists_lessons_for_formation(): void
    {
        $controller = new LessonController();

        Lesson::factory()->count(4)->create(['formation_id' => $this->formation->id]);

        $request = Request::create("/api/v1/lessons?formation_id={$this->formation->id}", 'GET');

        $response = $controller->index($request);

        $data = json_decode($response->getContent(), true);

        $this->assertCount(4, $data['data']);
    }

    #[Test]
    public function it_shows_lesson_details(): void
    {
        $controller = new LessonController();

        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
        ]);

        $response = $controller->show($lesson->id);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals($lesson->id, $data['data']['id']);
        $this->assertEquals($lesson->title, $data['data']['title']);
        $this->assertArrayHasKey('resources_count', $data['data']);
    }

    #[Test]
    public function it_creates_lesson(): void
    {
        $controller = new LessonController();

        $request = Request::create('/api/v1/lessons', 'POST', [
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
            'title' => 'Lesson 1: Introduction',
            'summary' => 'Learn the basics',
            'duration_seconds' => 600,
        ]);

        $response = $controller->store($request);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->status());
        $this->assertEquals('Lesson 1: Introduction', $data['data']['title']);
        $this->assertEquals('lesson-1-introduction', $data['data']['slug']);

        $this->assertDatabaseHas('lessons', [
            'formation_id' => $this->formation->id,
            'module_id' => $this->module->id,
            'title' => 'Lesson 1: Introduction',
        ]);
    }

    #[Test]
    public function it_auto_orders_new_lessons(): void
    {
        $controller = new LessonController();

        Lesson::factory()->create([
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
            'order' => 1,
        ]);

        $request = Request::create('/api/v1/lessons', 'POST', [
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
            'title' => 'Second Lesson',
        ]);

        $controller->store($request);

        $this->assertDatabaseHas('lessons', [
            'module_id' => $this->module->id,
            'title' => 'Second Lesson',
            'order' => 2,
        ]);
    }

    #[Test]
    public function it_updates_lesson(): void
    {
        $controller = new LessonController();

        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
        ]);

        $request = Request::create("/api/v1/lessons/{$lesson->id}", 'PATCH', [
            'title' => 'Updated Title',
            'is_published' => true,
        ]);

        $response = $controller->update($request, $lesson->id);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Updated Title', $data['data']['title']);
        $this->assertTrue($data['data']['is_published']);
    }

    #[Test]
    public function it_deletes_lesson(): void
    {
        $controller = new LessonController();

        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
        ]);

        $response = $controller->destroy($lesson->id);

        $this->assertEquals(204, $response->status());
        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
    }

    #[Test]
    public function it_publishes_lesson(): void
    {
        $controller = new LessonController();

        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
            'is_published' => false,
        ]);

        $response = $controller->publish($lesson->id);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['is_published']);
    }

    #[Test]
    public function it_unpublishes_lesson(): void
    {
        $controller = new LessonController();

        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
            'is_published' => true,
        ]);

        $response = $controller->unpublish($lesson->id);

        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['data']['is_published']);
    }

    #[Test]
    public function it_reorders_lessons(): void
    {
        $controller = new LessonController();

        $lesson1 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
            'order' => 1,
        ]);
        $lesson2 = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
            'order' => 2,
        ]);

        $request = Request::create('/api/v1/lessons/reorder', 'POST', [
            'formation_id' => $this->formation->id,
            'module_id' => $this->module->id,
            'lessons' => [
                ['id' => $lesson2->id, 'order' => 1],
                ['id' => $lesson1->id, 'order' => 2],
            ],
        ]);

        $response = $controller->reorder($request);

        $this->assertEquals(200, $response->status());

        $this->assertDatabaseHas('lessons', ['id' => $lesson2->id, 'order' => 1]);
        $this->assertDatabaseHas('lessons', ['id' => $lesson1->id, 'order' => 2]);
    }

    #[Test]
    public function it_returns_lesson_resources(): void
    {
        $controller = new LessonController();

        $lesson = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'formation_id' => $this->formation->id,
        ]);

        \App\Models\LessonResource::factory()->count(2)->create(['lesson_id' => $lesson->id]);

        $response = $controller->resources($lesson->id);

        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']);
    }
}
