<?php

namespace App\Livewire\ChangeRequests\Concerns;

use App\Enums\RiskEvent;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait ValidatesChangeRequest
{
    /**
     * Aturan validasi bersama untuk form create dan edit RFC.
     */
    protected function changeRequestRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'application_id' => 'required|exists:applications,id',
            'change_type' => 'required|in:standard,normal,emergency',
            'description' => 'required|string',
            'reason_for_change' => 'required|string|min:10',
            'legal_basis' => 'required|string',
            'change_risk' => 'required|string',
            'risk_event' => ['nullable', 'string', Rule::enum(RiskEvent::class)],
            'impact_description' => 'nullable|string',
            'head_of_department_approved' => 'boolean',
            'attachments' => 'array|max:5',
            'attachments.*' => 'file|mimes:pdf|max:5120',
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
            'legal_basis.required' => 'Dasar hukum wajib diisi.',
            'change_risk.required' => 'Risiko perubahan wajib diisi.',
            'risk_event.Illuminate\Validation\Rules\Enum' => 'Register risiko tidak valid.',
            'head_of_department_approved.accepted' => 'Pernyataan persetujuan Pimpinan (Eselon II) wajib dikonfirmasi sebelum mengajukan RFC.',
            'attachments.max' => 'Maksimal 5 lampiran per RFC.',
            'attachments.*.mimes' => 'Lampiran harus berformat PDF.',
            'attachments.*.max' => 'Setiap lampiran maksimal 5 MB.',
        ];
    }

    protected function validateChangeRequest(int $existingAttachmentCount = 0): void
    {
        // Register risiko bersifat opsional; nilai kosong dinormalisasi menjadi null.
        $this->risk_event = filled($this->risk_event) ? $this->risk_event : null;

        $this->validate($this->changeRequestRules(), $this->changeRequestMessages());

        if ($existingAttachmentCount + count($this->attachments) > 5) {
            throw ValidationException::withMessages([
                'attachments' => 'Total lampiran per RFC maksimal 5 file.',
            ]);
        }
    }

    /**
     * Syarat tambahan saat RFC benar-benar diajukan, bukan saat disimpan draft.
     * Seluruh kegagalan dikumpulkan agar pemohon melihat semua yang perlu dilengkapi.
     */
    protected function validateForSubmission(int $existingAttachmentCount = 0): void
    {
        $errors = [];

        if (! $this->head_of_department_approved) {
            $errors['head_of_department_approved'] =
                $this->changeRequestMessages()['head_of_department_approved.accepted'];
        }

        if ($existingAttachmentCount + count($this->attachments) < 1) {
            $errors['attachments'] = 'Minimal 1 lampiran PDF wajib diunggah saat mengajukan RFC.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function removePendingAttachment(int $index): void
    {
        if (! array_key_exists($index, $this->attachments)) {
            return;
        }

        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }
}
