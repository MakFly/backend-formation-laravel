<?php

declare(strict_types=1);

namespace App\Actions\LessonResource;

use App\Models\Lesson;
use App\Models\LessonResource;

final readonly class CreateLessonResourceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data, Lesson $lesson): LessonResource
    {
        $resource = new LessonResource($data);
        $resource->lesson_id = $lesson->id;

        if (! isset($data['order'])) {
            $lastOrder = LessonResource::where('lesson_id', $lesson->id)->max('order');
            $resource->order = $lastOrder ? $lastOrder + 1 : 0;
        }

        $resource->save();

        return $resource;
    }
}
