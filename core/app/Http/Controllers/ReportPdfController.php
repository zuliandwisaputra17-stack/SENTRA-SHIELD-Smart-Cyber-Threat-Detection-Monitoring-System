<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ekspor PDF LAPSIT (FR-12). Setiap unduhan tercatat di audit log (FR-11). */
class ReportPdfController extends Controller
{
    public function __invoke(Report $report, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('kelola-lapsit');
        Gate::authorize('view', $report->incident);
        abort_if($report->pdf_path === null || ! Storage::disk('local')->exists($report->pdf_path), 404, 'File PDF LAPSIT tidak ditemukan.');

        $number = $report->number();
        $audit->record('lapsit_diekspor', $report->incident, ['nomor' => $number, 'laporan_id' => $report->id, 'sha256_pdf' => $report->sha256]);

        return Storage::disk('local')->download($report->pdf_path, str_replace('/', '-', $number).'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
