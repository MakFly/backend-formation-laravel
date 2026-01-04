<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\CreateModuleAction;
use App\Models\Formation;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateModuleActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_module(): void
    {
        $formation = Formation::factory()->create();
        $action = new CreateModuleAction();

        $data = [
            'title' => 'Introduction Module',
            'description' => 'Introduction to the course',
            'type' => 'video',
            'order' => 1,
        ];

        $module = $action($data, $formation);

        $this->assertInstanceOf(Module::class, $module);
        $this->assertEquals($formation->id, $module->formation_id);
        $this->assertEquals('Introduction Module', $module->title);
        $this->assertEquals('introduction-module', $module->slug);
        $this->assertEquals('Introduction to the course', $module->description);
        $this->assertEquals(1, $module->order);
    }

    #[Test]
    public function it_auto_generates_slug(): void
    {
        $formation = Formation::factory()->create();
        $action = new CreateModuleAction();

        $data = [
            'title' => 'Advanced Concepts',
        ];

        $module = $action($data, $formation);

        $this->assertEquals('advanced-concepts', $module->slug);
    }

    #[Test]
    public function it_uses_custom_slug(): void
    {
        $formation = Formation::factory()->create();
        $action = new CreateModuleAction();

        $data = [
            'title' => 'Module Title',
            'slug' => 'custom-slug',
        ];

        $module = $action($data, $formation);

        $this->assertEquals('custom-slug', $module->slug);
    }
}
