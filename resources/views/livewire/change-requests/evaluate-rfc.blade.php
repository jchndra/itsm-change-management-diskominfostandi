@php
    $implementation = $rfc->implementation;
    $implementationEvidences = $rfc->evidences->where('evidence_type', 'implementation')->values();
@endphp

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('change-requests.show', $rfc->id) }}"
                    class="text-slate-400 transition hover:text-slate-600" wire:navigate>
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-slate-900">Catat Evaluasi</h1>
            </div>
            <p class="mt-1 pl-9 text-sm text-slate-500">Evaluasi implementasi untuk {{ $rfc->displayIdentifier() }}</p>
        </div>
        <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
    </div>

    @error('status')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
            {{ $message }}</div>
    @enderror

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="mb-4 border-b border-slate-100 pb-2 text-lg font-bold text-slate-800">Ringkasan Hasil Implementasi</h2>

        @if ($implementation)
            @include('livewire.change-requests.partials.implementation-summary', [
                'implementation' => $implementation,
                'evidences' => $implementationEvidences,
            ])
        @else
            <p class="text-sm text-slate-500">RFC ini belum memiliki catatan implementasi.</p>
        @endif
    </div>

    <form wire:submit="saveEvaluation"
        class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div>
            <label for="evaluation_notes" class="mb-1 block text-sm font-semibold text-slate-700">
                Catatan Evaluasi <span class="text-red-500">*</span>
            </label>
            <textarea id="evaluation_notes" wire:model="evaluation_notes" rows="6" maxlength="2000"
                class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                placeholder="Tuliskan penilaian terhadap hasil implementasi, pengujian, bukti, dan tindak lanjut..."></textarea>
            <p class="mt-1 text-xs text-slate-500">Minimal 20 karakter, maksimal 2.000 karakter.</p>
            @error('evaluation_notes')
                <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <a href="{{ route('change-requests.show', $rfc->id) }}" wire:navigate
                class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
                Batal
            </a>
            <button type="submit" wire:loading.attr="disabled" wire:target="saveEvaluation"
                class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                <span wire:loading.remove wire:target="saveEvaluation">Simpan Evaluasi</span>
                <span wire:loading wire:target="saveEvaluation">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
