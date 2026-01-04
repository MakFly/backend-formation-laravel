<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type,
            'company_name' => $this->company_name,
            'company_siret' => $this->company_siret,
            'company_tva_number' => $this->company_tva_number,
            'full_name' => $this->full_name,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            // Dynamic stats (added by AdminCustomerController)
            'enrollments_count' => $resource->enrollments_count ?? null,
            'active_enrollments_count' => $resource->active_enrollments_count ?? null,
            'completed_enrollments_count' => $resource->completed_enrollments_count ?? null,
            'total_spent' => isset($resource->total_spent) ? (float) $resource->total_spent : null,
            'relationships' => [
                'enrollments' => $this->when($this->relationLoaded('enrollments'), fn() => EnrollmentResource::collection($this->enrollments)),
            ],
        ];
    }
}
