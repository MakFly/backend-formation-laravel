<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FormationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'pricing_tier' => $this->pricing_tier?->value,
            'price' => (float) $this->price,
            'mode' => $this->mode,
            'thumbnail' => $this->thumbnail,
            'video_trailer' => $this->video_trailer,
            'tags' => $this->tags,
            'objectives' => $this->objectives,
            'requirements' => $this->requirements,
            'target_audience' => $this->target_audience,
            'language' => $this->language,
            'subtitles' => $this->subtitles,
            'difficulty_level' => $this->difficulty_level,
            'duration_hours' => $this->duration_hours,
            'duration_minutes' => $this->duration_minutes,
            'total_duration' => $this->total_duration,
            'instructor_name' => $this->instructor_name,
            'instructor_title' => $this->instructor_title,
            'instructor_avatar' => $this->instructor_avatar,
            'instructor_bio' => $this->instructor_bio,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'is_published' => $this->is_published,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->toIso8601String(),
            'enrollment_count' => $this->enrollment_count,
            'average_rating' => (float) $this->average_rating,
            'review_count' => $this->review_count,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            // Dynamic stats (added by AdminFormationController)
            'enrollments_count' => $resource->enrollments_count ?? null,
            'active_enrollments_count' => $resource->active_enrollments_count ?? null,
            'completed_enrollments_count' => $resource->completed_enrollments_count ?? null,
            'revenue' => isset($resource->revenue) ? (float) $resource->revenue : null,
            'refunds' => isset($resource->refunds) ? (float) $resource->refunds : null,
            'lessons_count' => $resource->lessons_count ?? null,
            'modules_count' => $resource->modules_count ?? null,
            'relationships' => [
                'category' => $this->when($this->relationLoaded('category'), fn () => CategoryResource::make($this->category)),
                'modules' => $this->when($this->relationLoaded('modules'), fn () => ModuleResource::collection($this->modules)),
                'enrollments' => $this->when($this->relationLoaded('enrollments'), fn () => EnrollmentResource::collection($this->enrollments)),
                'payments' => $this->when($this->relationLoaded('payments'), fn () => PaymentResource::collection($this->payments)),
            ],
        ];
    }
}
