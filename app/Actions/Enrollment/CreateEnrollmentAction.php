<?php

declare(strict_types=1);

namespace App\Actions\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Formation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class CreateEnrollmentAction
{
    /**
     * Create a new enrollment for a customer in a formation.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Customer $customer, Formation $formation, array $data = []): Enrollment
    {
        // Check if customer is already enrolled
        $existingEnrollment = Enrollment::where('customer_id', $customer->id)
            ->where('formation_id', $formation->id)
            ->where('status', '!=', EnrollmentStatus::CANCELLED)
            ->first();

        if ($existingEnrollment !== null) {
            throw new RuntimeException('Customer is already enrolled in this formation');
        }

        return DB::transaction(function () use ($customer, $formation, $data) {
            $enrollment = new Enrollment;
            $enrollment->customer_id = $customer->id;
            $enrollment->formation_id = $formation->id;
            $enrollment->status = EnrollmentStatus::PENDING;
            $enrollment->progress_percentage = 0;
            $enrollment->enrolled_at = now();
            $enrollment->access_count = 0;
            $enrollment->amount_paid = $data['amount_paid'] ?? 0;
            $enrollment->payment_reference = $data['payment_reference'] ?? null;
            $enrollment->metadata = $data['metadata'] ?? null;
            $enrollment->save();

            // Update formation enrollment count
            DB::table('formations')
                ->where('id', $formation->id)
                ->increment('enrollment_count');

            return $enrollment->load(['customer', 'formation']);
        });
    }
}
