<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property string $module_id
 * @property string $formation_id
 * @property string $title
 * @property string|null $slug
 * @property string|null $summary
 * @property string|null $content
 * @property string|null $video_url
 * @property string|null $thumbnail
 * @property int|null $duration_seconds
 * @property bool $is_preview
 * @property bool $is_published
 * @property int $order
 * @property array|null $content_mdx
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $duration_human
 * @property int|null $resources_count
 * @property-read Module $module
 * @property-read Formation $formation
 * @property-read Collection<int, LessonResource> $resources
 *
 * @method static Builder|Lesson published()
 * @method static Builder|Lesson preview()
 * @method static Builder|Lesson byModule(string $moduleId)
 * @method static Builder|Lesson byFormation(string $formationId)
 * @method static Builder|Lesson ordered()
 * @method static Builder|Lesson newModelQuery()
 * @method static Builder|Lesson newQuery()
 * @method static Builder|Lesson query()
 * @method static Builder|Lesson where($column, $operator = null, $value = null)
 * @method static Builder|Lesson find($id)
 * @method static Builder|Lesson findOrFail($id)
 * @method static Lesson create(array $attributes = [])
 */
final class Lesson extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'module_id',
        'formation_id',
        'title',
        'slug',
        'summary',
        'content',
        'video_url',
        'thumbnail',
        'duration_seconds',
        'is_preview',
        'is_published',
        'order',
        'content_mdx',
        'metadata',
    ];

    protected $casts = [
        'module_id' => 'string',
        'formation_id' => 'string',
        'duration_seconds' => 'integer',
        'is_preview' => 'boolean',
        'is_published' => 'boolean',
        'order' => 'integer',
        'content_mdx' => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'metadata',
    ];

    protected $appends = [
        'duration_human',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class)->orderBy('order');
    }

    public function getDurationHumanAttribute(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }

        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);
        $seconds = $this->duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopePreview($query)
    {
        return $query->where('is_preview', true);
    }

    public function scopeByModule($query, string $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeByFormation($query, string $formationId)
    {
        return $query->where('formation_id', $formationId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/'.$value) : null,
        );
    }

    protected function videoUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?: null,
        );
    }
}
