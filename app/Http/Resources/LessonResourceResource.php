<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LessonResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'title' => $this->title,
            'type' => $this->type?->value,
            'file_path' => $this->file_path,
            'file_url' => $this->file_url,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'size_human' => $this->human_readable_size,
            'duration' => $this->duration,
            'duration_human' => $this->human_readable_duration,
            'description' => $this->description,
            'is_downloadable' => $this->is_downloadable,
            'order' => $this->order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
