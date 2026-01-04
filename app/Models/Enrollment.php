<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $customer_id
 * @property string $formation_id
 * @property EnrollmentStatus $status
 * @property int $progress_percentage
 * @property Carbon|null $enrolled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $last_accessed_at
 * @property int $access_count
 * @property float|null $amount_paid
 * @property string|null $payment_reference
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Customer $customer
 * @property-read Formation $formation
 * @property-read Collection<int, LessonProgress> $lessonProgress
 *
 * @method static Builder|Enrollment byStatus(EnrollmentStatus|string $status)
 * @method static Builder|Enrollment active()
 * @method static Builder|Enrollment completed()
 * @method static Builder|Enrollment pending()
 * @method static Builder|Enrollment byCustomer(string $customerId)
 * @method static Builder|Enrollment byFormation(string $formationId)
 * @method static Builder|Enrollment recent()
 * @method static Builder|Enrollment newModelQuery()
 * @method static Builder|Enrollment newQuery()
 * @method static Builder|Enrollment query()
 * @method static Builder|Enrollment whereId($value)
 * @method static Builder|Enrollment where($column, $operator = null, $value = null)
 * @method static Builder|Enrollment find($id)
 * @method static Builder|Enrollment findOrFail($id)
 * @method static Enrollment create(array $attributes = [])
 * @method static Enrollment firstOrCreate(array $attributes, array $values = [])
 * @method static Enrollment firstOrNew(array $attributes, array $values = [])
 * @method static int count()
 * @method static Builder|Enrollment when($value, $callback, $default = null)
 */
final class Enrollment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'customer_id',
        'formation_id',
        'status',
        'progress_percentage',
        'enrolled_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'last_accessed_at',
        'access_count',
        'amount_paid',
        'payment_reference',
        'metadata',
    ];

    protected $casts = [
        'customer_id' => 'string',
        'formation_id' => 'string',
        'status' => EnrollmentStatus::class,
        'progress_percentage' => 'integer',
        'enrolled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
        'amount_paid' => 'decimal:2',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Formation, $this> */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /** @return HasMany<LessonProgress, $this> */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    // Scopes
    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeByStatus(Builder $query, EnrollmentStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof EnrollmentStatus ? $status->value : $status);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::ACTIVE);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::COMPLETED);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::PENDING);
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
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('enrolled_at', 'desc');
    }

    // Accessors & Helpers
    public function isActive(): bool
    {
        return $this->status === EnrollmentStatus::ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === EnrollmentStatus::COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === EnrollmentStatus::PENDING;
    }

    public function getProgressPercentageAttribute(): int
    {
        return $this->attributes['progress_percentage'] ?? 0;
    }

    public function markAsActive(): void
    {
        $this->status = EnrollmentStatus::ACTIVE;
        if ($this->started_at === null) {
            $this->started_at = now();
        }
        $this->save();
    }

    public function markAsCompleted(): void
    {
        $this->status = EnrollmentStatus::COMPLETED;
        $this->progress_percentage = 100;
        $this->completed_at = now();
        $this->save();
    }

    public function markAsCancelled(): void
    {
        $this->status = EnrollmentStatus::CANCELLED;
        $this->cancelled_at = now();
        $this->save();
    }

    public function recordAccess(): void
    {
        $this->last_accessed_at = now();
        $this->access_count = ($this->access_count ?? 0) + 1;
        $this->save();
    }

    public function updateProgress(int $percentage): void
    {
        $this->progress_percentage = max(0, min(100, $percentage));
        if ($this->progress_percentage >= 100 && ! $this->isCompleted()) {
            $this->markAsCompleted();
        } else {
            $this->save();
        }
    }

    public function calculateProgressFromLessons(): int
    {
        $totalLessons = $this->formation->lessons()->count();
        if ($totalLessons === 0) {
            return 0;
        }

        $completedLessons = $this->lessonProgress()->where('status', 'completed')->count();

        return (int) round(($completedLessons / $totalLessons) * 100);
    }

    public function refreshProgress(): void
    {
        $this->updateProgress($this->calculateProgressFromLessons());
    }
}
