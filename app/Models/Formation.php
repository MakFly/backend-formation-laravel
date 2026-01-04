<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PricingTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $category_id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property PricingTier $pricing_tier
 * @property float $price
 * @property string|null $mode
 * @property string|null $thumbnail
 * @property string|null $video_trailer
 * @property array|null $tags
 * @property array|null $objectives
 * @property array|null $requirements
 * @property array|null $target_audience
 * @property string|null $language
 * @property array|null $subtitles
 * @property string|null $difficulty_level
 * @property int $duration_hours
 * @property int $duration_minutes
 * @property string|null $instructor_name
 * @property string|null $instructor_title
 * @property string|null $instructor_avatar
 * @property string|null $instructor_bio
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 * @property bool $is_published
 * @property bool $is_featured
 * @property Carbon|null $published_at
 * @property int $enrollment_count
 * @property float $average_rating
 * @property int $review_count
 * @property array|null $content_mdx
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $total_duration
 * @property int|null $enrollments_count
 * @property int|null $active_enrollments_count
 * @property int|null $completed_enrollments_count
 * @property float|null $revenue
 * @property float|null $refunds
 * @property int|null $lessons_count
 * @property int|null $modules_count
 * @property-read Category|null $category
 * @property-read Collection<int, Module> $modules
 * @property-read Collection<int, Lesson> $lessons
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read Collection<int, Payment> $payments
 *
 * @method static Builder|Formation published()
 * @method static Builder|Formation featured()
 * @method static Builder|Formation byCategory(string $categoryId)
 * @method static Builder|Formation byPricingTier(PricingTier $tier)
 * @method static Builder|Formation byMode(string $mode)
 * @method static Builder|Formation free()
 * @method static Builder|Formation paid()
 * @method static Builder|Formation byDifficulty(string $level)
 * @method static Builder|Formation byLanguage(string $language)
 * @method static Builder|Formation withTags(array $tags)
 * @method static Builder|Formation newModelQuery()
 * @method static Builder|Formation newQuery()
 * @method static Builder|Formation query()
 * @method static Builder|Formation where($column, $operator = null, $value = null)
 * @method static Builder|Formation withCount($relations)
 * @method static Builder|Formation find($id)
 * @method static Builder|Formation findOrFail($id)
 * @method static Formation create(array $attributes = [])
 */
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
