<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Formation;
use App\Models\Module;

final readonly class ReorderModulesAction
{
    /**
     * @param  array<int, array{ id: string, order: int }>  $orders
     */
    public function __invoke(Formation $formation, array $orders): bool
    {
        foreach ($orders as $item) {
            Module::where('formation_id', $formation->id)
                ->where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return true;
    }
}
