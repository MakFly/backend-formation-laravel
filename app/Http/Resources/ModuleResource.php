<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        return [
            'id' => $this->id,
            'formation_id' => $this->formation_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type?->value,
            'order' => $this->order,
            'is_published' => $this->is_published,
            'is_free' => $this->is_free,
            'published_at' => $this->published_at?->toIso8601String(),
            'lesson_count' => $this->lesson_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Dynamic stats (added by controllers)
            'lessons_count' => $resource->lessons_count ?? null,
            'total_duration' => $resource->total_duration ?? null,
            'published_lessons_count' => $resource->published_lessons_count ?? null,
            'relationships' => [
                'formation' => $this->when($this->relationLoaded('formation'), fn() => FormationResource::make($this->formation)),
                'lessons' => $this->when($this->relationLoaded('lessons'), fn() => LessonResource::collection($this->lessons)),
            ],
        ];
    }
}
