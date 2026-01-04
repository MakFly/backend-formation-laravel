<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModuleType;
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
 * @property string $formation_id
 * @property string $title
 * @property string|null $slug
 * @property string|null $description
 * @property ModuleType $type
 * @property int $order
 * @property bool $is_published
 * @property bool $is_free
 * @property Carbon|null $published_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int $lesson_count
 * @property-read int|null $lessons_count
 * @property-read int|null $total_duration
 * @property-read int|null $published_lessons_count
 * @property-read Formation $formation
 * @property-read Collection<int, Lesson> $lessons
 *
 * @method static Builder|Module published()
 * @method static Builder|Module free()
 * @method static Builder|Module byFormation(string $formationId)
 * @method static Builder|Module ordered()
 * @method static Builder|Module newModelQuery()
 * @method static Builder|Module newQuery()
 * @method static Builder|Module query()
 * @method static Builder|Module where($column, $operator = null, $value = null)
 * @method static Builder|Module find($id)
 * @method static Builder|Module findOrFail($id)
 * @method static Module create(array $attributes = [])
 */
final class Module extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'formation_id',
        'title',
        'slug',
        'description',
        'type',
        'order',
        'is_published',
        'is_free',
        'published_at',
        'metadata',
    ];

    protected $casts = [
        'formation_id' => 'string',
        'type' => ModuleType::class,
        'order' => 'integer',
        'is_published' => 'boolean',
        'is_free' => 'boolean',
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'metadata',
    ];

    protected $appends = [
        'lesson_count',
    ];

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function getLessonCountAttribute(): int
    {
        return $this->lessons()->count();
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    public function scopeByFormation($query, string $formationId)
    {
        return $query->where('formation_id', $formationId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
