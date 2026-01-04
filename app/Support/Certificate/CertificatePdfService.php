<?php

declare(strict_types=1);

namespace App\Support\Certificate;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CertificatePdfService
{
    private const string DISK = 'public';
    private const string DIRECTORY = 'certificates';

    public function generate(Certificate $certificate): string
    {
        // Ensure directory exists
        Storage::disk(self::DISK)->makeDirectory(self::DIRECTORY);

        // Generate PDF content (HTML template)
        $html = $this->generateHtml($certificate);

        // For now, store as HTML (PDF generation would require a library like dompdf/snappy)
        $filename = $certificate->pdf_filename;
        $path = self::DIRECTORY . '/' . $filename;

        Storage::disk(self::DISK)->put($path, $html);

        return Storage::disk(self::DISK)->path($path);
    }

    public function regenerate(Certificate $certificate): string
    {
        // Delete old PDF if exists
        if ($certificate->pdf_path && Storage::disk(self::DISK)->exists($certificate->pdf_path)) {
            Storage::disk(self::DISK)->delete($certificate->pdf_path);
        }

        return $this->generate($certificate);
    }

    public function delete(Certificate $certificate): void
    {
        if ($certificate->pdf_path && Storage::disk(self::DISK)->exists($certificate->pdf_path)) {
            Storage::disk(self::DISK)->delete($certificate->pdf_path);
        }

        $certificate->update([
            'pdf_path' => null,
            'pdf_size_bytes' => null,
        ]);
    }

    private function generateHtml(Certificate $certificate): string
    {
        $verificationUrl = $certificate->generateVerificationUrl();
        $instructorName = $certificate->instructor_name ?? 'Direction';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - {$certificate->formation_title}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Georgia', serif;
            background: #f5f5f5;
            padding: 40px;
        }

        .certificate {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 60px;
            border: 10px solid #1a1a1a;
            position: relative;
        }

        .certificate::before {
            content: '';
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 2px solid #c9a227;
            pointer-events: none;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 48px;
            color: #1a1a1a;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        .header .subtitle {
            font-size: 18px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .content {
            text-align: center;
            margin: 50px 0;
        }

        .content p {
            font-size: 20px;
            color: #333;
            line-height: 1.8;
            margin-bottom: 20px;
        }

        .content .student-name {
            font-size: 36px;
            font-weight: bold;
            color: #1a1a1a;
            margin: 30px 0;
            font-family: 'Times New Roman', serif;
        }

        .content .formation-title {
            font-size: 28px;
            color: #c9a227;
            font-weight: bold;
            margin: 20px 0;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid #ddd;
        }

        .footer .info {
            font-size: 14px;
            color: #666;
        }

        .footer .certificate-number {
            font-size: 14px;
            color: #666;
            text-align: right;
        }

        .seal {
            position: absolute;
            bottom: 80px;
            right: 80px;
            width: 100px;
            height: 100px;
            border: 3px solid #c9a227;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #c9a227;
            text-align: center;
            font-weight: bold;
            transform: rotate(-15deg);
        }

        .signature {
            margin-top: 40px;
            text-align: center;
        }

        .signature .line {
            width: 200px;
            height: 1px;
            background: #333;
            margin: 0 auto 10px;
        }

        .signature .name {
            font-size: 16px;
            color: #333;
        }

        .verification {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="header">
            <h1>Certificat</h1>
            <div class="subtitle">De Réussite</div>
        </div>

        <div class="content">
            <p>Certifie que</p>
            <p class="student-name">{$certificate->student_name}</p>
            <p>a complété avec succès la formation</p>
            <p class="formation-title">{$certificate->formation_title}</p>
            <p>Le {$certificate->completion_date->locale('fr_FR')->format('d F Y')}</p>
        </div>

        <div class="signature">
            <div class="line"></div>
            <div class="name">{$instructorName}</div>
        </div>

        <div class="seal">
            CERTIFIÉ
        </div>

        <div class="footer">
            <div class="info">
                <p>Certificat #{$certificate->certificate_number}</p>
                <p>Délivré le {$certificate->issued_at->locale('fr_FR')->format('d/m/Y')}</p>
            </div>
        </div>

        <div class="verification">
            Vérifier l'authenticité : {$verificationUrl}
        </div>
    </div>
</body>
</html>
HTML;
    }
}
