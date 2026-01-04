<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentType: string
{
    case ENROLLMENT = 'enrollment';
    case SUBSCRIPTION = 'subscription';
    case RENEWAL = 'renewal';
}
