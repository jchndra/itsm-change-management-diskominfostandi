<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\ChangeRequestAttachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChangeRequestAttachmentController extends Controller
{
    use AuthorizesRequests;

    public function show(ChangeRequest $rfc, ChangeRequestAttachment $attachment): BinaryFileResponse
    {
        $this->authorize('view', $rfc);

        abort_unless((int) $attachment->change_request_id === (int) $rfc->id, 404);

        $path = Storage::disk('local')->path($attachment->path);

        abort_unless(is_file($path), 404);

        if (request()->boolean('download')) {
            return response()->download($path, $attachment->original_name, [
                'Content-Type' => $attachment->mime_type,
            ]);
        }

        return response()->file($path, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
        ]);
    }
}
