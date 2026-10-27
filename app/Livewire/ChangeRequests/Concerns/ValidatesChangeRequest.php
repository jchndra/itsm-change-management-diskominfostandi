<?php

namespace App\Livewire\ChangeRequests\Concerns;

use App\Models\ChangeRequest;
use Illuminate\Validation\ValidationException;

trait ValidatesChangeRequest
{
    /**
     * Aturan validasi bersama untuk form create dan edit RFC.
     */
    protected function changeRequestRules(): array
    {
        $approvalLetterMimes = implode(',', (array) config('rfc.approval_letter.mimes', ['pdf']));
        $approvalLetterMaxKb = (int) config('rfc.approval_letter.max_kb', 5120);

        return [
            'title' => 'required|string|max:255',
            'application_id' => 'required|exists:applications,id',
            'change_type' => 'required|in:standard,normal,emergency',
            'description' => 'required|string',
            'reason_for_change' => 'required|string|min:10',
            'impact_description' => 'nullable|string',
            'target_implementation_date' => 'required|date|after:now',
            'is_physical_letter' => 'boolean',
            'physical_letter_number' => 'nullable|required_if:is_physical_letter,true|string|max:100',
            'attachments' => 'array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
            'approval_letter' => "nullable|file|mimes:{$approvalLetterMimes}|max:{$approvalLetterMaxKb}",
        ];
    }

    /**
     * Pesan validasi bersama agar perilaku create dan edit konsisten.
     */
    protected function changeRequestMessages(): array
    {
        return [
            'title.required' => 'Judul perubahan wajib diisi.',
            'application_id.required' => 'Silakan pilih aplikasi/sistem tujuan.',
            'change_type.required' => 'Kategori perubahan wajib dipilih.',
            'change_type.in' => 'Kategori perubahan tidak valid.',
            'description.required' => 'Deskripsi perubahan wajib diisi.',
            'reason_for_change.required' => 'Alasan perubahan wajib diisi.',
            'reason_for_change.min' => 'Alasan perubahan minimal 10 karakter.',
            'target_implementation_date.required' => 'Usulan tanggal dan jam wajib diisi.',
            'target_implementation_date.after' => 'Usulan tanggal dan jam harus di masa mendatang.',
            'physical_letter_number.required_if' => 'Nomor surat fisik wajib diisi jika opsi surat fisik dipilih.',
            'physical_letter_number.max' => 'Nomor surat tidak boleh lebih dari 100 karakter.',
            'attachments.max' => 'Maksimal 5 lampiran per RFC.',
            'attachments.*.mimes' => 'Lampiran harus berformat PDF, JPG, JPEG, atau PNG.',
            'attachments.*.max' => 'Setiap lampiran maksimal 5 MB.',
            'approval_letter.mimes' => 'Surat persetujuan harus berformat PDF.',
            'approval_letter.max' => 'Surat persetujuan maksimal 5 MB.',
        ];
    }

    protected function validateChangeRequest(int $existingAttachmentCount = 0): void
    {
        $this->validate($this->changeRequestRules(), $this->changeRequestMessages());

        if ($existingAttachmentCount + count($this->attachments) > 5) {
            throw ValidationException::withMessages([
                'attachments' => 'Total lampiran per RFC maksimal 5 file.',
            ]);
        }
    }

    protected function validatePhysicalLetterAttachment(int $existingAttachmentCount = 0): void
    {
        if ($this->is_physical_letter && $existingAttachmentCount + count($this->attachments) < 1) {
            throw ValidationException::withMessages([
                'attachments' => 'Lampiran surat/nota dinas wajib diunggah saat mengajukan RFC.',
            ]);
        }
    }

    /**
     * Surat persetujuan wajib tersedia saat submit. Aturan ini sementara
     * berlaku untuk semua kategori dan dapat diubah lewat config saat SOP final.
     */
    protected function validateApprovalLetterForSubmission(bool $hasExisting, bool $hasPending): void
    {
        if (! config('rfc.approval_letter.required_for_submission', true)) {
            return;
        }

        $requiredChangeTypes = (array) config('rfc.approval_letter.change_types', []);

        if ($requiredChangeTypes !== [] && ! in_array($this->change_type, $requiredChangeTypes, true)) {
            return;
        }

        if ($hasExisting || $hasPending) {
            return;
        }

        $maxKb = (int) config('rfc.approval_letter.max_kb', 5120);

        throw ValidationException::withMessages([
            'approval_letter' => "Surat persetujuan bertanda tangan wajib diunggah saat mengajukan RFC (PDF, maks. {$maxKb} KB).",
        ]);
    }

    protected function hasExistingApprovalLetter(?ChangeRequest $rfc): bool
    {
        return $rfc !== null && $rfc->approvalLetters()->exists();
    }

    public function removePendingAttachment(int $index): void
    {
        if (! array_key_exists($index, $this->attachments)) {
            return;
        }

        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function removePendingApprovalLetter(): void
    {
        $this->approval_letter = null;
    }
}
