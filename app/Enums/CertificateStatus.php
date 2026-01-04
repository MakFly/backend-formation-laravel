<?php

declare(strict_types=1);

namespace App\Enums;

enum CertificateStatus: string
{
    case ACTIVE = 'active';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';
}
