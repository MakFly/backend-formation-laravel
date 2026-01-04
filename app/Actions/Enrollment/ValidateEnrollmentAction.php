<?php

declare(strict_types=1);

namespace App\Actions\Enrollment;

use App\Enums\PricingTier;
use App\Models\Enrollment;
use RuntimeException;

final readonly class ValidateEnrollmentAction
{
    /**
     * Validate an enrollment and mark it as active.
     * Checks if payment is confirmed and formation is accessible.
     */
    public function __invoke(Enrollment $enrollment): Enrollment
    {
        if (! $enrollment->isPending()) {
            throw new RuntimeException('Enrollment is not in pending status');
        }

        // Check if enrollment can be activated
        $formation = $enrollment->formation;
        $isPaid = $enrollment->amount_paid > 0;
        $isFreeTier = $formation->pricing_tier === PricingTier::FREE;
        $isFreeFormation = $formation->price === 0.0;

        // Activate if free or paid
        if ($isFreeTier || $isFreeFormation || $isPaid) {
            $enrollment->markAsActive();

            return $enrollment->fresh();
        }

        throw new RuntimeException('Enrollment cannot be activated: payment required');
    }
}
