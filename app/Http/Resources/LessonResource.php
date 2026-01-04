<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LessonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        return [
            'id' => $this->id,
            'module_id' => $this->module_id,
            'formation_id' => $this->formation_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'content' => $this->content,
            'video_url' => $this->video_url,
            'thumbnail' => $this->thumbnail,
            'duration_seconds' => $this->duration_seconds,
            'duration_human' => $this->duration_human,
            'is_preview' => $this->is_preview,
            'is_published' => $this->is_published,
            'order' => $this->order,
            'content_mdx' => $this->content_mdx,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            // Dynamic stats (added by controllers)
            'resources_count' => $resource->resources_count ?? null,
            'relationships' => [
                'module' => $this->when($this->relationLoaded('module'), fn () => ModuleResource::make($this->module)),
                'formation' => $this->when($this->relationLoaded('formation'), fn () => FormationResource::make($this->formation)),
                'resources' => $this->when($this->relationLoaded('resources'), fn () => LessonResourceResource::collection($this->resources)),
            ],
        ];
    }
}
