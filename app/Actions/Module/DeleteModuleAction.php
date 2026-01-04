<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Module;

final readonly class DeleteModuleAction
{
    public function __invoke(Module $module): bool
    {
        return $module->delete();
    }
}
