<?php

namespace App\Services;

use App\Models\ChangeRequest;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class RfcResultReportService
{
    /**
     * Render dokumen hasil RFC sebagai PDF biner.
     */
    public function render(ChangeRequest $rfc, User $viewer): string
    {
        $rfc = ChangeRequest::query()
            ->with([
                'submittedBy.opd',
                'application',
                'decisions.recordedBy',
                'riskAssessment.assessedBy',
                'implementation.recordedBy',
                'implementation.evaluatedBy',
                'releaseTasks.releaseTeamMember',
                'releaseTasks.updates.user',
                'closedBy',
                'satisfaction',
                'attachments',
                'evidences',
            ])
            ->findOrFail($rfc->getKey());

        $this->ensureStorageDirectories();

        $html = view('pdf.rfc-result', [
            'rfc' => $rfc,
            'viewer' => $viewer,
        ])->render();

        return Pdf::setOptions([
            'isRemoteEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
            'fontDir' => base_path('vendor/dompdf/dompdf/lib/fonts'),
            'fontCache' => storage_path('fonts'),
            'tempDir' => storage_path('app/dompdf-tmp'),
            'chroot' => [public_path(), storage_path()],
        ], true)
            ->loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->output();
    }

    public function filename(ChangeRequest $rfc): string
    {
        $identifier = preg_replace('/[^A-Za-z0-9._-]+/', '-', $rfc->displayIdentifier()) ?? '';
        $identifier = trim($identifier, '-_.');

        if ($identifier === '') {
            $identifier = 'RFC-'.$rfc->getKey();
        }

        return 'Hasil-RFC-'.$identifier.'.pdf';
    }

    private function ensureStorageDirectories(): void
    {
        File::ensureDirectoryExists(storage_path('fonts'));
        File::ensureDirectoryExists(storage_path('app/dompdf-tmp'));
    }
}
