<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CertificateFormationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'pricing_tier' => $this->pricing_tier?->value,
            'price' => $this->price,
            'mode' => $this->mode,
            'thumbnail' => $this->thumbnail,
        ];
    }
}
