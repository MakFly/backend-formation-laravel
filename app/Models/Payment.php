<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $customer_id
 * @property string|null $enrollment_id
 * @property string|null $formation_id
 * @property PaymentType $type
 * @property PaymentStatus $status
 * @property string|null $stripe_payment_intent_id
 * @property string|null $stripe_checkout_session_id
 * @property string|null $stripe_invoice_id
 * @property string|null $stripe_subscription_id
 * @property float $amount
 * @property float $amount_refunded
 * @property string $currency
 * @property string|null $payment_method_type
 * @property string|null $description
 * @property string|null $failure_reason
 * @property string|null $failure_code
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $stripe_response
 * @property Carbon|null $paid_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int $amount_in_cents
 * @property-read int $amount_refunded_in_cents
 * @property-read float $refundable_amount
 * @property-read Customer $customer
 * @property-read Enrollment|null $enrollment
 * @property-read Formation|null $formation
 *
 * @method static Builder|Payment byStatus(PaymentStatus|string $status)
 * @method static Builder|Payment byType(PaymentType|string $type)
 * @method static Builder|Payment byCustomer(string $customerId)
 * @method static Builder|Payment byFormation(string $formationId)
 * @method static Builder|Payment byStripePaymentIntent(string $paymentIntentId)
 * @method static Builder|Payment byStripeCheckoutSession(string $sessionId)
 * @method static Builder|Payment pending()
 * @method static Builder|Payment processing()
 * @method static Builder|Payment completed()
 * @method static Builder|Payment failed()
 * @method static Builder|Payment refunded()
 * @method static Builder|Payment partiallyRefunded()
 * @method static Builder|Payment successful()
 * @method static Builder|Payment notFailed()
 * @method static Builder|Payment newModelQuery()
 * @method static Builder|Payment newQuery()
 * @method static Builder|Payment query()
 * @method static Builder|Payment where($column, $operator = null, $value = null)
 * @method static Builder|Payment whereIn($column, $values)
 * @method static Builder|Payment when($value, $callback)
 * @method static Builder|Payment find($id)
 * @method static Builder|Payment findOrFail($id)
 * @method static Payment create(array $attributes = [])
 */
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
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<Formation, $this> */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    // Scopes
    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeByStatus(Builder $query, PaymentStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof PaymentStatus ? $status->value : $status);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeByType(Builder $query, PaymentType|string $type): Builder
    {
        return $query->where('type', $type instanceof PaymentType ? $type->value : $type);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeByCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeByFormation(Builder $query, string $formationId): Builder
    {
        return $query->where('formation_id', $formationId);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeByStripePaymentIntent(Builder $query, string $paymentIntentId): Builder
    {
        return $query->where('stripe_payment_intent_id', $paymentIntentId);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeByStripeCheckoutSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('stripe_checkout_session_id', $sessionId);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::PROCESSING);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::FAILED);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::REFUNDED);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePartiallyRefunded(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::PARTIALLY_REFUNDED);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeNotFailed(Builder $query): Builder
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
