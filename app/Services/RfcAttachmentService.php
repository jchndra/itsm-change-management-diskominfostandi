<?php

namespace App\Services;

use App\Models\ChangeRequest;
use App\Models\ChangeRequestAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RfcAttachmentService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, ChangeRequestAttachment>
     */
    public function storeMany(
        ChangeRequest $rfc,
        array $files,
        User $actor,
        string $documentType = ChangeRequestAttachment::TYPE_SUPPORTING,
    ): array {
        $attachments = [];

        try {
            foreach ($files as $file) {
                $attachments[] = $this->store($rfc, $file, $actor, $documentType);
            }
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($attachments);

            throw $exception;
        }

        return $attachments;
    }

    public function store(
        ChangeRequest $rfc,
        UploadedFile $file,
        User $actor,
        string $documentType = ChangeRequestAttachment::TYPE_SUPPORTING,
    ): ChangeRequestAttachment {
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        $path = $file->storeAs(
            'rfc-attachments/'.$rfc->id,
            Str::uuid()->toString().'.'.$extension,
            'local',
        );

        if (! is_string($path)) {
            throw new RuntimeException('Lampiran RFC gagal disimpan.');
        }

        try {
            $attachment = $rfc->attachments()->create([
                'uploaded_by_id' => $actor->id,
                'document_type' => $documentType,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
            ]);

            activity('rfc')
                ->performedOn($rfc)
                ->causedBy($actor)
                ->withProperties([
                    'event' => 'attachment_added',
                    'document_type' => $documentType,
                    'file_name' => $attachment->original_name,
                    'attachment_id' => $attachment->id,
                ])
                ->log($documentType === ChangeRequestAttachment::TYPE_APPROVAL_LETTER
                    ? 'Surat persetujuan RFC ditambahkan'
                    : 'Lampiran RFC ditambahkan');
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return $attachment;
    }

    public function delete(ChangeRequestAttachment $attachment, User $actor): void
    {
        if ($attachment->isApprovalLetter()) {
            throw new RuntimeException('Surat persetujuan bertanda tangan tidak dapat dihapus.');
        }

        $rfc = $attachment->changeRequest;

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        if ($rfc) {
            activity('rfc')
                ->performedOn($rfc)
                ->causedBy($actor)
                ->withProperties([
                    'event' => 'attachment_deleted',
                    'document_type' => $attachment->document_type,
                    'file_name' => $attachment->original_name,
                    'attachment_id' => $attachment->id,
                ])
                ->log('Lampiran RFC dihapus');
        }
    }

    /**
     * @param  array<int, ChangeRequestAttachment>  $attachments
     */
    public function deleteStoredFiles(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            Storage::disk('local')->delete($attachment->path);
        }
    }
}
