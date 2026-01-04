<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string $type
 * @property string|null $company_name
 * @property string|null $company_siret
 * @property string|null $company_tva_number
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read int|null $enrollments_count
 * @property-read int|null $active_enrollments_count
 * @property-read int|null $completed_enrollments_count
 * @property-read float|null $total_spent
 * @property-read Payment|null $last_payment
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read Collection<int, Payment> $payments
 *
 * @method static Builder|Customer individual()
 * @method static Builder|Customer company()
 * @method static Builder|Customer byEmail(string $email)
 * @method static Builder|Customer newModelQuery()
 * @method static Builder|Customer newQuery()
 * @method static Builder|Customer query()
 * @method static Builder|Customer where($column, $operator = null, $value = null)
 * @method static Builder|Customer find($id)
 * @method static Builder|Customer findOrFail($id)
 * @method static Customer create(array $attributes = [])
 */
final class Customer extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'type',
        'company_name',
        'company_siret',
        'company_tva_number',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isIndividual(): bool
    {
        return $this->type === 'individual';
    }

    public function isCompany(): bool
    {
        return $this->type === 'company';
    }

    public function scopeIndividual($query)
    {
        return $query->where('type', 'individual');
    }

    public function scopeCompany($query)
    {
        return $query->where('type', 'company');
    }

    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }
}
