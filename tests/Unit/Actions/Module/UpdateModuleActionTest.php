<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\UpdateModuleAction;
use App\Models\Formation;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UpdateModuleActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_a_module(): void
    {
        $formation = Formation::factory()->create();
        $module = Module::factory()->make(['formation_id' => $formation->id]);
        $module->save();
        $action = new UpdateModuleAction();

        $data = [
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ];

        $updatedModule = $action($module, $data);

        $this->assertEquals('Updated Title', $updatedModule->title);
        $this->assertEquals('updated-title', $updatedModule->slug);
        $this->assertEquals('Updated description', $updatedModule->description);
    }

    #[Test]
    public function it_preserves_slug_when_title_not_changed(): void
    {
        $formation = Formation::factory()->create();
        $module = Module::factory()->make([
            'formation_id' => $formation->id,
            'title' => 'Original Title',
            'slug' => 'original-slug',
        ]);
        $module->save();
        $action = new UpdateModuleAction();

        $updatedModule = $action($module, ['description' => 'New description']);

        $this->assertEquals('original-slug', $updatedModule->slug);
    }

    #[Test]
    public function it_preserves_custom_slug(): void
    {
        $formation = Formation::factory()->create();
        $module = Module::factory()->make([
            'formation_id' => $formation->id,
            'title' => 'Title',
            'slug' => 'custom-slug',
        ]);
        $module->save();
        $action = new UpdateModuleAction();

        $data = [
            'title' => 'New Title',
            'slug' => 'another-custom-slug',
        ];

        $updatedModule = $action($module, $data);

        $this->assertEquals('another-custom-slug', $updatedModule->slug);
    }
}
