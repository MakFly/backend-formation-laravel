<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Certificate\GenerateCertificateAction;
use App\Actions\Certificate\RevokeCertificateAction;
use App\Actions\Certificate\VerifyCertificateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RevokeCertificateRequest;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Support\Certificate\CertificatePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CertificateController extends Controller
{
    public function __construct(
        private CertificatePdfService $pdfService
    ) {}

    /**
     * List certificates with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Certificate::query();

        if ($request->has('customer_id')) {
            $query->byCustomer($request->input('customer_id'));
        }

        if ($request->has('formation_id')) {
            $query->byFormation($request->input('formation_id'));
        }

        if ($request->has('status')) {
            $query->byStatus($request->input('status'));
        }

        $certificates = $query->with(['customer', 'formation'])
            ->orderBy('issued_at', 'desc')
            ->paginate($request->input('per_page', 30));

        return CertificateResource::collection($certificates)->toResponse($request);
    }

    /**
     * Get single certificate.
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $certificate = Certificate::with(['enrollment', 'customer', 'formation'])->findOrFail($id);

        return CertificateResource::make($certificate)->toResponse($request);
    }

    /**
     * Generate certificate for a completed enrollment.
     */
    public function generate(string $enrollmentId, GenerateCertificateAction $action): JsonResponse
    {
        $enrollment = Enrollment::with(['customer', 'formation'])->findOrFail($enrollmentId);

        $certificate = $action($enrollment);

        return CertificateResource::make($certificate)->toResponse(request())
            ->setStatusCode(201);
    }

    /**
     * Verify certificate by verification code (public endpoint).
     */
    public function verify(string $code, VerifyCertificateAction $action): JsonResponse
    {
        $result = $action($code);

        if (! $result['valid']) {
            return response()->json([
                'valid' => false,
                'reason' => $result['reason'],
                'certificate' => $result['certificate'] ? CertificateResource::make($result['certificate']) : null,
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'certificate' => CertificateResource::make($result['certificate']),
        ]);
    }

    /**
     * Verify certificate by certificate number (public endpoint).
     */
    public function verifyByNumber(string $number, VerifyCertificateAction $action): JsonResponse
    {
        $result = $action->byNumber($number);

        if (! $result['valid']) {
            return response()->json([
                'valid' => false,
                'reason' => $result['reason'],
                'certificate' => $result['certificate'] ? CertificateResource::make($result['certificate']) : null,
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'certificate' => CertificateResource::make($result['certificate']),
        ]);
    }

    /**
     * Revoke a certificate.
     */
    public function revoke(string $id, RevokeCertificateRequest $request, RevokeCertificateAction $action): JsonResponse
    {
        $certificate = Certificate::findOrFail($id);

        $certificate = $action($certificate, $request->input('reason'));

        return CertificateResource::make($certificate)->toResponse(request());
    }

    /**
     * Regenerate certificate PDF.
     */
    public function regenerate(string $id): JsonResponse
    {
        $certificate = Certificate::with(['enrollment', 'customer', 'formation'])->findOrFail($id);

        $this->pdfService->regenerate($certificate);

        return CertificateResource::make($certificate->fresh())->toResponse(request());
    }

    /**
     * Download certificate PDF.
     */
    public function download(string $id): StreamedResponse|JsonResponse
    {
        $certificate = Certificate::findOrFail($id);

        if (! $certificate->pdf_path || ! Storage::disk('public')->exists($certificate->pdf_path)) {
            return response()->json([
                'error' => 'PDF not found',
                'message' => 'Certificate PDF has not been generated yet',
            ], 404);
        }

        return Storage::disk('public')->download($certificate->pdf_path, $certificate->pdf_filename);
    }

    /**
     * Get certificates for a specific customer.
     */
    public function getByCustomer(string $customerId, Request $request): JsonResponse
    {
        $certificates = Certificate::byCustomer($customerId)
            ->with(['formation'])
            ->orderBy('issued_at', 'desc')
            ->paginate($request->input('per_page', 30));

        return CertificateResource::collection($certificates)->toResponse($request);
    }

    /**
     * Get certificates for a specific formation.
     */
    public function getByFormation(string $formationId, Request $request): JsonResponse
    {
        $certificates = Certificate::byFormation($formationId)
            ->with(['customer'])
            ->orderBy('issued_at', 'desc')
            ->paginate($request->input('per_page', 30));

        return CertificateResource::collection($certificates)->toResponse($request);
    }
}
