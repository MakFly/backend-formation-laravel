<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Module;
use Illuminate\Support\Str;

final readonly class UpdateModuleAction
{
    public function __invoke(Module $module, array $data): Module
    {
        if (isset($data['title']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $module->fill($data);
        $module->save();

        return $module->fresh();
    }
}
