<?php

declare(strict_types=1);

namespace App\Actions\Certificate;

use App\Models\Certificate;
use RuntimeException;

final readonly class RevokeCertificateAction
{
    /**
     * Revoke a certificate.
     */
    public function __invoke(Certificate $certificate, ?string $reason = null): Certificate
    {
        if ($certificate->isRevoked()) {
            throw new RuntimeException('Certificate is already revoked');
        }

        $certificate->markAsRevoked($reason);

        return $certificate->fresh()->load(['enrollment', 'customer', 'formation']);
    }
}
