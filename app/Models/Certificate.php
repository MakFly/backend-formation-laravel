<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CertificateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    // Scopes
    public function scopeByStatus($query, CertificateStatus|string $status)
    {
        return $query->where('status', $status instanceof CertificateStatus ? $status->value : $status);
    }

    public function scopeActive($query)
    {
        return $query->where('status', CertificateStatus::ACTIVE);
    }

    public function scopeRevoked($query)
    {
        return $query->where('status', CertificateStatus::REVOKED);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', CertificateStatus::EXPIRED);
    }

    public function scopeByCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByFormation($query, string $formationId)
    {
        return $query->where('formation_id', $formationId);
    }

    public function scopeByVerificationCode($query, string $code)
    {
        return $query->where('verification_code', $code);
    }

    public function scopeByCertificateNumber($query, string $number)
    {
        return $query->where('certificate_number', $number);
    }

    public function scopeNotExpired($query)
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
