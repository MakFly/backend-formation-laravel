<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LessonResourceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $lesson_id
 * @property string $title
 * @property LessonResourceType $type
 * @property string|null $file_path
 * @property string|null $file_url
 * @property string|null $file_name
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property int|null $duration
 * @property string|null $description
 * @property bool $is_downloadable
 * @property int $order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $human_readable_size
 * @property-read string|null $human_readable_duration
 * @property-read Lesson $lesson
 *
 * @method static Builder|LessonResource byLesson(string $lessonId)
 * @method static Builder|LessonResource downloadable()
 * @method static Builder|LessonResource ordered()
 * @method static Builder|LessonResource byType(LessonResourceType $type)
 * @method static Builder|LessonResource newModelQuery()
 * @method static Builder|LessonResource newQuery()
 * @method static Builder|LessonResource query()
 * @method static Builder|LessonResource where($column, $operator = null, $value = null)
 * @method static Builder|LessonResource find($id)
 * @method static Builder|LessonResource findOrFail($id)
 * @method static LessonResource create(array $attributes = [])
 */
final class LessonResource extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'lesson_id',
        'title',
        'type',
        'file_path',
        'file_url',
        'file_name',
        'mime_type',
        'file_size',
        'duration',
        'description',
        'is_downloadable',
        'order',
        'metadata',
    ];

    protected $casts = [
        'lesson_id' => 'string',
        'type' => LessonResourceType::class,
        'file_size' => 'integer',
        'duration' => 'integer',
        'is_downloadable' => 'boolean',
        'order' => 'integer',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'metadata',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function scopeByLesson($query, string $lessonId)
    {
        return $query->where('lesson_id', $lessonId);
    }

    public function scopeDownloadable($query)
    {
        return $query->where('is_downloadable', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeByType($query, LessonResourceType $type)
    {
        return $query->where('type', $type->value);
    }

    public function getHumanReadableSizeAttribute(): string
    {
        if (! $this->file_size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $this->file_size > 0 ? floor(log($this->file_size, 1024)) : 0;

        return number_format($this->file_size / (1024 ** $power), 2).' '.$units[$power];
    }

    public function getHumanReadableDurationAttribute(): ?string
    {
        if (! $this->duration) {
            return null;
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
