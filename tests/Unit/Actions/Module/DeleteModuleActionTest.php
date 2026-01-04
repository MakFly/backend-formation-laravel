<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\DeleteModuleAction;
use App\Models\Formation;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DeleteModuleActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_a_module(): void
    {
        $formation = Formation::factory()->create();
        $module = Module::factory()->make(['formation_id' => $formation->id]);
        $module->save();
        $action = new DeleteModuleAction();

        $result = $action($module);

        $this->assertTrue($result);
        // Avec SoftDeletes, la ligne est marquée comme supprimée mais reste en base
        $this->assertSoftDeleted('modules', ['id' => $module->id]);
    }

    #[Test]
    public function it_soft_deletes_a_module(): void
    {
        $formation = Formation::factory()->create();
        $module = Module::factory()->make(['formation_id' => $formation->id]);
        $module->save();
        $action = new DeleteModuleAction();

        $action($module);

        $this->assertSoftDeleted('modules', ['id' => $module->id]);
    }
}
