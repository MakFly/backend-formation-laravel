<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CertificateStatus;
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
 * @property string $customer_id
 * @property string $formation_id
 * @property string $certificate_number
 * @property CertificateStatus $status
 * @property Carbon|null $issued_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
 * @property string $verification_code
 * @property string $student_name
 * @property string $formation_title
 * @property string|null $instructor_name
 * @property Carbon|null $completion_date
 * @property string|null $pdf_path
 * @property int|null $pdf_size_bytes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $download_url
 * @property-read string $pdf_filename
 * @property-read Enrollment $enrollment
 * @property-read Customer $customer
 * @property-read Formation $formation
 *
 * @method static Builder|Certificate byStatus(CertificateStatus|string $status)
 * @method static Builder|Certificate active()
 * @method static Builder|Certificate revoked()
 * @method static Builder|Certificate expired()
 * @method static Builder|Certificate byCustomer(string $customerId)
 * @method static Builder|Certificate byFormation(string $formationId)
 * @method static Builder|Certificate byVerificationCode(string $code)
 * @method static Builder|Certificate byCertificateNumber(string $number)
 * @method static Builder|Certificate notExpired()
 * @method static Builder|Certificate newModelQuery()
 * @method static Builder|Certificate newQuery()
 * @method static Builder|Certificate query()
 * @method static Builder|Certificate where($column, $operator = null, $value = null)
 * @method static Builder|Certificate find($id)
 * @method static Builder|Certificate findOrFail($id)
 * @method static Certificate create(array $attributes = [])
 */
final class Certificate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'enrollment_id',
        'customer_id',
        'formation_id',
        'certificate_number',
        'status',
        'issued_at',
        'expires_at',
        'revoked_at',
        'revoked_reason',
        'verification_code',
        'student_name',
        'formation_title',
        'instructor_name',
        'completion_date',
        'pdf_path',
        'pdf_size_bytes',
        'metadata',
    ];

    protected $casts = [
        'status' => CertificateStatus::class,
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'completion_date' => 'date',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'metadata',
    ];

    // Relations
    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

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

    // Scopes
    /**
     * @param Builder<Certificate> $query
     * @return Builder<Certificate>
     */
    public function scopeByStatus(Builder $query, CertificateStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof CertificateStatus ? $status->value : $status);
    }

    /**
     * @param Builder<Certificate> $query
     * @return Builder<Certificate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CertificateStatus::ACTIVE);
    }

    /**
     * @param Builder<Certificate> $query
     * @return Builder<Certificate>
     */
    public function scopeRevoked(Builder $query): Builder
    {
        return $query->where('status', CertificateStatus::REVOKED);
    }

    /**
     * @param Builder<Certificate> $query
     * @return Builder<Certificate>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', CertificateStatus::EXPIRED);
    }

    /**
     * @param Builder<Certificate> $query
     * @return Builder<Certificate>
     */
    public function scopeByCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * @param Builder<Certificate> $query
     * @return Builder<Certificate>
     */
    public function scopeByFormation(Builder $query, string $formationId): Builder
    {
        return $query->where('formation_id', $formationId);
    }

    /**
     * @param Builder<Certificate> $query
     * @return Builder<Certificate>
     */
    public function scopeByVerificationCode(Builder $query, string $code): Builder
    {
        return $query->where('verification_code', $code);
    }

    /**
     * @param Builder<Certificate> $query
     * @return Builder<Certificate>
     */
    public function scopeByCertificateNumber(Builder $query, string $number): Builder
    {
        return $query->where('certificate_number', $number);
    }

    /**
     * @param Builder<Certificate> $query
     * @return Builder<Certificate>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === CertificateStatus::ACTIVE;
    }

    public function isRevoked(): bool
    {
        return $this->status === CertificateStatus::REVOKED;
    }

    public function isExpired(): bool
    {
        if ($this->status === CertificateStatus::EXPIRED) {
            return true;
        }

        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->isActive() && ! $this->isExpired();
    }

    public function markAsRevoked(?string $reason = null): void
    {
        $this->status = CertificateStatus::REVOKED;
        $this->revoked_at = now();
        $this->revoked_reason = $reason;
        $this->save();
    }

    public function markAsExpired(): void
    {
        $this->status = CertificateStatus::EXPIRED;
        $this->save();
    }

    public function markAsActive(): void
    {
        $this->status = CertificateStatus::ACTIVE;
        $this->revoked_at = null;
        $this->revoked_reason = null;
        $this->save();
    }

    public function generateVerificationUrl(): string
    {
        return config('app.url').'/api/v1/certificates/verify/'.$this->verification_code;
    }

    public function getDownloadUrlAttribute(): string
    {
        return config('app.url').'/api/v1/certificates/'.$this->id.'/download';
    }

    public function getPdfFilenameAttribute(): string
    {
        return 'certificate-'.$this->certificate_number.'.pdf';
    }
}
