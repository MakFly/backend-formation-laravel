<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

final class Lesson extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

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
        if (!$this->duration_seconds) {
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
            get: fn ($value) => $value ? asset('storage/' . $value) : null,
        );
    }

    protected function videoUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?: null,
        );
    }
}
