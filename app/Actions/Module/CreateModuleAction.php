<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Formation;
use App\Models\Module;
use Illuminate\Support\Str;

final readonly class CreateModuleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data, Formation $formation): Module
    {
        if (! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $module = new Module($data);
        $module->formation_id = $formation->id;

        if (! isset($data['order'])) {
            $lastOrder = Module::where('formation_id', $formation->id)->max('order');
            $module->order = $lastOrder ? $lastOrder + 1 : 0;
        }

        $module->save();

        return $module;
    }
}
