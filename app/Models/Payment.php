<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Payment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'enrollment_id',
        'formation_id',
        'type',
        'status',
        'stripe_payment_intent_id',
        'stripe_checkout_session_id',
        'stripe_invoice_id',
        'stripe_subscription_id',
        'amount',
        'amount_refunded',
        'currency',
        'payment_method_type',
        'description',
        'failure_reason',
        'failure_code',
        'metadata',
        'stripe_response',
        'paid_at',
        'refunded_at',
        'failed_at',
    ];

    protected $casts = [
        'type' => PaymentType::class,
        'status' => PaymentStatus::class,
        'amount' => 'decimal:2',
        'amount_refunded' => 'decimal:2',
        'metadata' => 'array',
        'stripe_response' => 'array',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected $hidden = [
        'metadata',
        'stripe_response',
    ];

    // Relations
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    // Scopes
    public function scopeByStatus($query, PaymentStatus|string $status)
    {
        return $query->where('status', $status instanceof PaymentStatus ? $status->value : $status);
    }

    public function scopeByType($query, PaymentType|string $type)
    {
        return $query->where('type', $type instanceof PaymentType ? $type->value : $type);
    }

    public function scopeByCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByFormation($query, string $formationId)
    {
        return $query->where('formation_id', $formationId);
    }

    public function scopeByStripePaymentIntent($query, string $paymentIntentId)
    {
        return $query->where('stripe_payment_intent_id', $paymentIntentId);
    }

    public function scopeByStripeCheckoutSession($query, string $sessionId)
    {
        return $query->where('stripe_checkout_session_id', $sessionId);
    }

    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', PaymentStatus::PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', PaymentStatus::FAILED);
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', PaymentStatus::REFUNDED);
    }

    public function scopePartiallyRefunded($query)
    {
        return $query->where('status', PaymentStatus::PARTIALLY_REFUNDED);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    public function scopeNotFailed($query)
    {
        return $query->whereNot('status', PaymentStatus::FAILED);
    }

    // Helpers
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === PaymentStatus::PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }

    public function isRefunded(): bool
    {
        return $this->status === PaymentStatus::REFUNDED;
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->status === PaymentStatus::PARTIALLY_REFUNDED;
    }

    public function isSuccessful(): bool
    {
        return $this->isCompleted() || $this->isRefunded() || $this->isPartiallyRefunded();
    }

    public function markAsProcessing(): void
    {
        $this->status = PaymentStatus::PROCESSING;
        $this->save();
    }

    public function markAsCompleted(?string $paymentMethodType = null): void
    {
        $this->status = PaymentStatus::COMPLETED;
        $this->paid_at = now();
        $this->payment_method_type = $paymentMethodType;
        $this->save();
    }

    public function markAsFailed(?string $reason = null, ?string $code = null): void
    {
        $this->status = PaymentStatus::FAILED;
        $this->failed_at = now();
        $this->failure_reason = $reason;
        $this->failure_code = $code;
        $this->save();
    }

    public function markAsRefunded(float $amount): void
    {
        $this->amount_refunded = $this->amount_refunded + $amount;

        if ($this->amount_refunded >= $this->amount) {
            $this->status = PaymentStatus::REFUNDED;
        } else {
            $this->status = PaymentStatus::PARTIALLY_REFUNDED;
        }

        $this->refunded_at = now();
        $this->save();
    }

    public function getAmountInCentsAttribute(): int
    {
        return (int) round($this->amount * 100);
    }

    public function getAmountRefundedInCentsAttribute(): int
    {
        return (int) round($this->amount_refunded * 100);
    }

    public function getRefundableAmountAttribute(): float
    {
        return max(0, $this->amount - $this->amount_refunded);
    }

    public function canBeRefunded(): bool
    {
        return ($this->isCompleted() || $this->isPartiallyRefunded()) && $this->refundable_amount > 0;
    }
}
