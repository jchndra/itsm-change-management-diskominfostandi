<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\Evidence;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImplementationEvidenceController extends Controller
{
    use AuthorizesRequests;

    public function show(ChangeRequest $rfc, Evidence $evidence): BinaryFileResponse
    {
        $this->authorize('viewEvidence', $rfc);

        abort_unless((int) $evidence->change_request_id === (int) $rfc->id, 404);

        $path = Storage::disk('local')->path($evidence->file_path);

        // Bukti lama (mis. uat_signoff) masih berada di disk public. Baca sebagai
        // cadangan tanpa memindahkan atau mengubah data lamanya.
        if (! is_file($path)) {
            $path = Storage::disk('public')->path($evidence->file_path);
        }

        abort_unless(is_file($path), 404);

        if (request()->boolean('download')) {
            return response()->download($path, $evidence->original_filename, [
                'Content-Type' => $evidence->mime_type,
            ]);
        }

        return response()->file($path, [
            'Content-Type' => $evidence->mime_type,
            'Content-Disposition' => 'inline; filename="'.$evidence->original_filename.'"',
        ]);
    }
}
