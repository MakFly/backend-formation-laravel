<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'enrollment_id' => $this->enrollment_id,
            'formation_id' => $this->formation_id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'amount' => (float) $this->amount,
            'amount_refunded' => (float) $this->amount_refunded,
            'currency' => $this->currency,
            'payment_method_type' => $this->payment_method_type,
            'description' => $this->description,
            'refundable_amount' => (float) $resource->refundable_amount,
            'can_be_refunded' => $resource->canBeRefunded(),
            'stripe_checkout_session_id' => $this->stripe_checkout_session_id,
            'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'relationships' => [
                'customer' => $this->when($this->relationLoaded('customer'), fn() => CustomerResource::make($this->customer)),
                'enrollment' => $this->when($this->relationLoaded('enrollment'), fn() => EnrollmentResource::make($this->enrollment)),
                'formation' => $this->when($this->relationLoaded('formation'), fn() => FormationResource::make($this->formation)),
            ],
        ];
    }
}
