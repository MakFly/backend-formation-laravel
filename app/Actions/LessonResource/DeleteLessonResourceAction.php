<?php

declare(strict_types=1);

namespace App\Actions\LessonResource;

use App\Models\LessonResource;

final readonly class DeleteLessonResourceAction
{
    public function __invoke(LessonResource $resource): bool
    {
        return $resource->delete();
    }
}
