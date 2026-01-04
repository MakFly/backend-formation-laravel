<?php

declare(strict_types=1);

namespace App\Actions\Certificate;

use App\Models\Certificate;

final readonly class VerifyCertificateAction
{
    /**
     * Verify a certificate by verification code.
     *
     * @return array{valid: bool, certificate: Certificate|null, reason: string|null}
     */
    public function __invoke(string $verificationCode): array
    {
        $certificate = Certificate::byVerificationCode($verificationCode)->first();

        if (! $certificate) {
            return [
                'valid' => false,
                'certificate' => null,
                'reason' => 'Certificate not found',
            ];
        }

        if ($certificate->isRevoked()) {
            return [
                'valid' => false,
                'certificate' => $certificate->load(['customer', 'formation']),
                'reason' => 'Certificate has been revoked',
            ];
        }

        if ($certificate->isExpired()) {
            return [
                'valid' => false,
                'certificate' => $certificate->load(['customer', 'formation']),
                'reason' => 'Certificate has expired',
            ];
        }

        return [
            'valid' => true,
            'certificate' => $certificate->load(['customer', 'formation']),
            'reason' => null,
        ];
    }

    /**
     * Verify a certificate by certificate number.
     *
     * @return array{valid: bool, certificate: Certificate|null, reason: string|null}
     */
    public function byNumber(string $certificateNumber): array
    {
        $certificate = Certificate::byCertificateNumber($certificateNumber)->first();

        if (! $certificate) {
            return [
                'valid' => false,
                'certificate' => null,
                'reason' => 'Certificate not found',
            ];
        }

        if ($certificate->isRevoked()) {
            return [
                'valid' => false,
                'certificate' => $certificate->load(['customer', 'formation']),
                'reason' => 'Certificate has been revoked',
            ];
        }

        if ($certificate->isExpired()) {
            return [
                'valid' => false,
                'certificate' => $certificate->load(['customer', 'formation']),
                'reason' => 'Certificate has expired',
            ];
        }

        return [
            'valid' => true,
            'certificate' => $certificate->load(['customer', 'formation']),
            'reason' => null,
        ];
    }
}
