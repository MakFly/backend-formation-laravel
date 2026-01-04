<?php

declare(strict_types=1);

namespace App\Actions\Certificate;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Support\Certificate\CertificatePdfService;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class GenerateCertificateAction
{
    public function __construct(
        private CertificatePdfService $pdfService
    ) {}

    /**
     * Generate a certificate for a completed enrollment.
     */
    public function __invoke(Enrollment $enrollment): Certificate
    {
        if (! $enrollment->isCompleted()) {
            throw new RuntimeException('Cannot generate certificate: enrollment is not completed');
        }

        // Check if certificate already exists
        $existing = Certificate::where('enrollment_id', $enrollment->id)->first();
        if ($existing && $existing->isValid()) {
            return $existing;
        }

        // Generate unique identifiers
        $certificateNumber = $this->generateCertificateNumber();
        $verificationCode = $this->generateVerificationCode();

        // Prepare certificate data
        $customer = $enrollment->customer;
        $formation = $enrollment->formation;

        $certificateData = [
            'enrollment_id' => $enrollment->id,
            'customer_id' => $customer->id,
            'formation_id' => $formation->id,
            'certificate_number' => $certificateNumber,
            'status' => CertificateStatus::ACTIVE->value,
            'issued_at' => now(),
            'expires_at' => null, // Certificates don't expire by default
            'verification_code' => $verificationCode,
            'student_name' => trim($customer->first_name.' '.$customer->last_name),
            'formation_title' => $formation->title,
            'instructor_name' => $formation->instructor_name,
            'completion_date' => $enrollment->completed_at?->toDateString() ?? now()->toDateString(),
        ];

        // Create certificate
        $certificate = Certificate::create($certificateData);

        // Generate PDF
        $pdfPath = $this->pdfService->generate($certificate);
        $pdfSize = filesize($pdfPath);

        $certificate->update([
            'pdf_path' => $pdfPath,
            'pdf_size_bytes' => $pdfSize,
        ]);

        return $certificate->load(['enrollment', 'customer', 'formation']);
    }

    private function generateCertificateNumber(): string
    {
        do {
            $number = 'CERT-'.strtoupper(Str::random(12));
        } while (Certificate::where('certificate_number', $number)->exists());

        return $number;
    }

    private function generateVerificationCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Certificate::where('verification_code', $code)->exists());

        return $code;
    }
}
