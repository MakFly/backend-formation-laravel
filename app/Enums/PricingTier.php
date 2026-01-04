<?php

declare(strict_types=1);

namespace App\Enums;

enum PricingTier: string
{
    case FREE = 'free';
    case BASIC = 'basic';
    case STANDARD = 'standard';
    case PREMIUM = 'premium';
    case ENTERPRISE = 'enterprise';
}
