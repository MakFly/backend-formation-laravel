<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PricingTier;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Formation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'summary',
        'description',
        'pricing_tier',
        'price',
        'mode',
        'thumbnail',
        'video_trailer',
        'tags',
        'objectives',
        'requirements',
        'target_audience',
        'language',
        'subtitles',
        'difficulty_level',
        'duration_hours',
        'duration_minutes',
        'instructor_name',
        'instructor_title',
        'instructor_avatar',
        'instructor_bio',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_published',
        'is_featured',
        'published_at',
        'enrollment_count',
        'average_rating',
        'review_count',
        'content_mdx',
        'metadata',
    ];

    protected $casts = [
        'category_id' => 'string',
        'pricing_tier' => PricingTier::class,
        'price' => 'decimal:2',
        'tags' => 'array',
        'objectives' => 'array',
        'requirements' => 'array',
        'target_audience' => 'array',
        'subtitles' => 'array',
        'duration_hours' => 'integer',
        'duration_minutes' => 'integer',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'enrollment_count' => 'integer',
        'average_rating' => 'decimal:1',
        'review_count' => 'integer',
        'content_mdx' => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'metadata',
    ];

    protected $appends = [
        'total_duration',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getTotalDurationAttribute(): string
    {
        $totalMinutes = $this->duration_hours * 60 + $this->duration_minutes;
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}min";
        }

        return "{$minutes}min";
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, string $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByPricingTier($query, PricingTier $tier)
    {
        return $query->where('pricing_tier', $tier->value);
    }

    public function scopeByMode($query, string $mode)
    {
        return $query->where('mode', $mode);
    }

    public function scopeFree($query)
    {
        return $query->where('price', 0)->orWhere('pricing_tier', PricingTier::FREE);
    }

    public function scopePaid($query)
    {
        return $query->where('price', '>', 0);
    }

    public function scopeByDifficulty($query, string $level)
    {
        return $query->where('difficulty_level', $level);
    }

    public function scopeByLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    public function scopeWithTags($query, array $tags)
    {
        return $query->whereJsonContains('tags', $tags);
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/'.$value) : null,
        );
    }

    protected function videoTrailer(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/'.$value) : null,
        );
    }

    protected function instructorAvatar(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/'.$value) : null,
        );
    }
}
