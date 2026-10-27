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
                <h1 class="text-2xl font-bold text-slate-900">Penutupan Tiket RFC</h1>
            </div>
            <p class="mt-1 pl-9 text-sm text-slate-500">Nomor RFC: {{ $rfc->displayIdentifier() }}</p>
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

    @if ($implementation?->evaluated_at)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6">
            <h2 class="mb-3 text-lg font-bold text-emerald-900">Catatan Evaluasi</h2>
            <p class="whitespace-pre-wrap text-sm text-emerald-950">{{ $implementation->evaluation_notes }}</p>
            <p class="mt-3 text-xs text-emerald-800">
                Dievaluasi oleh {{ $implementation->evaluatedBy?->name ?? 'Change Manager' }}
                &middot; {{ $implementation->evaluated_at->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
            </p>
        </div>
    @endif

    <form wire:submit="closeTicket"
        class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div>
            <label for="closure_notes" class="mb-1 block text-sm font-semibold text-slate-700">
                Catatan Penutupan <span class="text-red-500">*</span>
            </label>
            <textarea id="closure_notes" wire:model="closure_notes" rows="5" maxlength="1000"
                class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                placeholder="Tuliskan keterangan penutupan RFC..."></textarea>
            <p class="mt-1 text-xs text-slate-500">Minimal 10 karakter, maksimal 1.000 karakter.</p>
            @error('closure_notes')
                <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
            @enderror
        </div>

        <label for="confirmed" class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <input id="confirmed" type="checkbox" wire:model="confirmed"
                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm text-slate-700">Saya menyatakan RFC ini siap ditutup</span>
        </label>
        @error('confirmed')
            <span class="block text-xs text-rose-500">{{ $message }}</span>
        @enderror

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <a href="{{ route('change-requests.show', $rfc->id) }}" wire:navigate
                class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
                Batal
            </a>
            <button type="submit" wire:loading.attr="disabled" wire:target="closeTicket"
                class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                <span wire:loading.remove wire:target="closeTicket">Tutup Tiket RFC</span>
                <span wire:loading wire:target="closeTicket">Menutup...</span>
            </button>
        </div>
    </form>
</div>
