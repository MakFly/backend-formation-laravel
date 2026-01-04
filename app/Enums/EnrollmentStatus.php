<?php

declare(strict_types=1);

namespace App\Enums;

enum EnrollmentStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case SUSPENDED = 'suspended';
}
