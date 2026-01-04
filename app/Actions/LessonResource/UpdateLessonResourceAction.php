<?php

declare(strict_types=1);

namespace App\Actions\LessonResource;

use App\Models\LessonResource;

final readonly class UpdateLessonResourceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(LessonResource $resource, array $data): LessonResource
    {
        $resource->fill($data);
        $resource->save();

        return $resource->fresh();
    }
}
