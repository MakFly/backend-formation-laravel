<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LessonProgressStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $enrollment_id
 * @property string $lesson_id
 * @property LessonProgressStatus|null $status
 * @property int $progress_percentage
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $last_accessed_at
 * @property int $time_spent_seconds
 * @property int $access_count
 * @property int|null $current_position
 * @property bool $is_favorite
 * @property array|null $metadata
 * @property array|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $time_spent
 * @property-read Enrollment $enrollment
 * @property-read Lesson $lesson
 *
 * @method static Builder|LessonProgress byStatus(LessonProgressStatus|string $status)
 * @method static Builder|LessonProgress notStarted()
 * @method static Builder|LessonProgress inProgress()
 * @method static Builder|LessonProgress completed()
 * @method static Builder|LessonProgress byEnrollment(string $enrollmentId)
 * @method static Builder|LessonProgress byLesson(string $lessonId)
 * @method static Builder|LessonProgress favorites()
 * @method static Builder|LessonProgress newModelQuery()
 * @method static Builder|LessonProgress newQuery()
 * @method static Builder|LessonProgress query()
 * @method static Builder|LessonProgress where($column, $operator = null, $value = null)
 * @method static Builder|LessonProgress find($id)
 * @method static Builder|LessonProgress findOrFail($id)
 * @method static LessonProgress create(array $attributes = [])
 * @method static LessonProgress firstOrCreate(array $attributes, array $values = [])
 * @method static LessonProgress firstOrNew(array $attributes, array $values = [])
 */
final class LessonProgress extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'enrollment_id',
        'lesson_id',
        'status',
        'progress_percentage',
        'started_at',
        'completed_at',
        'last_accessed_at',
        'time_spent_seconds',
        'access_count',
        'current_position',
        'is_favorite',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'enrollment_id' => 'string',
        'lesson_id' => 'string',
        'status' => LessonProgressStatus::class,
        'progress_percentage' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'time_spent_seconds' => 'integer',
        'access_count' => 'integer',
        'current_position' => 'integer',
        'is_favorite' => 'boolean',
        'metadata' => 'array',
        'notes' => 'array',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    // Scopes
    public function scopeByStatus($query, LessonProgressStatus|string $status)
    {
        return $query->where('status', $status instanceof LessonProgressStatus ? $status->value : $status);
    }

    public function scopeNotStarted($query)
    {
        return $query->where('status', LessonProgressStatus::NOT_STARTED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', LessonProgressStatus::IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', LessonProgressStatus::COMPLETED);
    }

    public function scopeByEnrollment($query, string $enrollmentId)
    {
        return $query->where('enrollment_id', $enrollmentId);
    }

    public function scopeByLesson($query, string $lessonId)
    {
        return $query->where('lesson_id', $lessonId);
    }

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    // Accessors & Helpers
    public function isNotStarted(): bool
    {
        return $this->status === null || $this->status === LessonProgressStatus::NOT_STARTED;
    }

    public function isInProgress(): bool
    {
        return $this->status === LessonProgressStatus::IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === LessonProgressStatus::COMPLETED;
    }

    public function getTimeSpentAttribute(): string
    {
        $seconds = $this->time_spent_seconds ?? 0;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        }
        if ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        }

        return sprintf('%ds', $secs);
    }

    public function markAsInProgress(): void
    {
        $this->status = LessonProgressStatus::IN_PROGRESS;
        if ($this->started_at === null) {
            $this->started_at = now();
        }
        $this->last_accessed_at = now();
        $this->access_count = ($this->access_count ?? 0) + 1;
        $this->save();
    }

    public function markAsCompleted(): void
    {
        $this->status = LessonProgressStatus::COMPLETED;
        $this->progress_percentage = 100;
        $this->completed_at = $this->completed_at ?? now();
        $this->last_accessed_at = now();
        $this->save();
    }

    public function updateProgress(int $percentage, ?int $position = null): void
    {
        $this->progress_percentage = max(0, min(100, $percentage));

        if ($this->isNotStarted() && $this->progress_percentage > 0) {
            $this->status = LessonProgressStatus::IN_PROGRESS;
            if ($this->started_at === null) {
                $this->started_at = now();
            }
        }

        if ($position !== null) {
            $this->current_position = $position;
        }

        if ($this->progress_percentage >= 100 && ! $this->isCompleted()) {
            $this->markAsCompleted();
        } else {
            $this->last_accessed_at = now();
            $this->save();
        }
    }

    public function recordAccess(?int $position = null): void
    {
        $this->last_accessed_at = now();

        if ($position !== null) {
            $this->current_position = $position;
        }

        if ($this->isNotStarted()) {
            $this->markAsInProgress();
        } else {
            $this->access_count = ($this->access_count ?? 0) + 1;
            $this->save();
        }
    }

    public function addTimeSpent(int $seconds): void
    {
        $this->time_spent_seconds = ($this->time_spent_seconds ?? 0) + $seconds;
        $this->save();
    }

    public function toggleFavorite(): void
    {
        $this->is_favorite = ! $this->is_favorite;
        $this->save();
    }

    public function updateNotes(array $notes): void
    {
        $this->notes = $notes;
        $this->save();
    }
}
