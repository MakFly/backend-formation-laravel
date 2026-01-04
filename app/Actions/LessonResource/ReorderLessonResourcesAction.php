<?php

declare(strict_types=1);

namespace App\Actions\LessonResource;

use App\Models\Lesson;
use App\Models\LessonResource;

final readonly class ReorderLessonResourcesAction
{
    /**
     * @param  array<int, array{ id: string, order: int }>  $orders
     */
    public function __invoke(Lesson $lesson, array $orders): bool
    {
        foreach ($orders as $item) {
            LessonResource::where('lesson_id', $lesson->id)
                ->where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return true;
    }
}
