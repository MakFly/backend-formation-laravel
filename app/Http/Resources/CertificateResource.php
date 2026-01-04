<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'customer_id' => $this->customer_id,
            'formation_id' => $this->formation_id,
            'certificate_number' => $this->certificate_number,
            'status' => $this->status->value,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revoked_reason' => $this->revoked_reason,
            'verification_code' => $this->verification_code,
            'student_name' => $this->student_name,
            'formation_title' => $this->formation_title,
            'instructor_name' => $this->instructor_name,
            'completion_date' => $this->completion_date?->toIso8601String(),
            'pdf_path' => $this->pdf_path,
            'pdf_size_bytes' => $this->pdf_size_bytes,
            'verification_url' => $this->generateVerificationUrl(),
            'download_url' => $this->download_url,
            'pdf_filename' => $this->pdf_filename,
            'is_valid' => $this->isValid(),
            'is_revoked' => $this->isRevoked(),
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relations when loaded
            'enrollment' => CertificateEnrollmentResource::make($this->whenLoaded('enrollment')),
            'customer' => CertificateCustomerResource::make($this->whenLoaded('customer')),
            'formation' => CertificateFormationResource::make($this->whenLoaded('formation')),
        ];
    }
}
