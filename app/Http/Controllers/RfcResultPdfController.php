<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcResultReportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class RfcResultPdfController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(ChangeRequest $rfc, RfcResultReportService $report): Response
    {
        $this->authorize('exportResult', $rfc);

        /** @var User $viewer */
        $viewer = Auth::user();

        $pdf = $report->render($rfc, $viewer);
        $filename = $report->filename($rfc);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ])->setPrivate();
    }
}
