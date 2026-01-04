<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModuleType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
