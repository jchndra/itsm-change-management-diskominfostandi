<?php

namespace App\Livewire\ChangeRequests\Concerns;

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
            'reason_for_change' => 'required|string|min:10',
            'impact_description' => 'required|string|min:10',
            'target_implementation_date' => 'required|date|after:now',
            'is_physical_letter' => 'boolean',
            'physical_letter_number' => 'nullable|required_if:is_physical_letter,true|string|max:100',
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
            'reason_for_change.required' => 'Alasan perubahan wajib diisi.',
            'reason_for_change.min' => 'Alasan perubahan minimal 10 karakter.',
            'impact_description.required' => 'Deskripsi dampak sistem wajib diisi.',
            'impact_description.min' => 'Deskripsi dampak minimal 10 karakter.',
            'target_implementation_date.required' => 'Target tanggal pelaksanaan wajib diisi.',
            'target_implementation_date.after' => 'Tanggal pelaksanaan harus di masa mendatang.',
            'physical_letter_number.required_if' => 'Nomor surat fisik wajib diisi jika opsi surat fisik dipilih.',
            'physical_letter_number.max' => 'Nomor surat tidak boleh lebih dari 100 karakter.',
        ];
    }

    protected function validateChangeRequest(): void
    {
        $this->validate($this->changeRequestRules(), $this->changeRequestMessages());
    }
}
