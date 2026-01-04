<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LessonProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'lesson_id' => $this->lesson_id,
            'status' => $this->status->value,
            'progress_percentage' => $this->progress_percentage,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'last_accessed_at' => $this->last_accessed_at?->toIso8601String(),
            'time_spent_seconds' => $this->time_spent_seconds,
            'time_spent_human' => $this->time_spent,
            'access_count' => $this->access_count,
            'current_position' => $this->current_position,
            'is_favorite' => $this->is_favorite,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relations
            'enrollment' => EnrollmentResource::make($this->whenLoaded('enrollment')),
            'lesson' => LessonResource::make($this->whenLoaded('lesson')),
        ];
    }
}
