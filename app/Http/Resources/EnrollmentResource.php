<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EnrollmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'formation_id' => $this->formation_id,
            'status' => $this->status->value,
            'progress_percentage' => $this->progress_percentage,
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'last_accessed_at' => $this->last_accessed_at?->toIso8601String(),
            'access_count' => $this->access_count,
            'amount_paid' => (float) $this->amount_paid,
            'payment_reference' => $this->payment_reference,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relations
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'formation' => FormationResource::make($this->whenLoaded('formation')),
            'lesson_progress' => LessonProgressResource::collection($this->whenLoaded('lessonProgress')),
        ];
    }
}
