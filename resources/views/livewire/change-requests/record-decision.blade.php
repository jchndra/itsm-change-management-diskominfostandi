<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('change-requests.show', $rfc->id) }}"
                    class="text-slate-400 transition hover:text-slate-600" wire:navigate>
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-slate-900">Pencatatan Keputusan Change Manager</h1>
            </div>
            <p class="mt-1 pl-9 text-sm text-slate-500">
                Keputusan resmi Change Manager untuk {{ $rfc->displayIdentifier() }}
            </p>
        </div>
        <a href="{{ route('change-requests.show', $rfc->id) }}"
            class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
            wire:navigate>
            Batal
        </a>
    </div>

    @include('livewire.change-requests.partials.rfc-summary', ['rfc' => $rfc])

    @error('status')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
            {{ $message }}
        </div>
    @enderror

    @unless ($rfc->riskAssessment)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-700">
            Analisis risiko belum dilakukan. Keputusan Change Manager belum dapat direkam.
        </div>
    @endunless

    <form wire:submit.prevent="saveDecision"
        class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <span class="mb-1 block text-sm font-semibold text-slate-700">Pencatat Keputusan</span>
                <p class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-700">
                    Dicatat atas nama: {{ $recordedBy?->name }}, {{ filled($recordedBy?->position) ? $recordedBy->position : 'Change Manager' }}
                </p>
            </div>

            <div>
                <label for="decisionStatus" class="mb-1 block text-sm font-semibold text-slate-700">
                    Keputusan Change Manager *
                </label>
                <select id="decisionStatus" wire:model.live="decisionStatus"
                    class="w-full rounded-lg border px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 {{ $errors->has('decisionStatus') ? 'border-red-500' : 'border-slate-300' }}">
                    @foreach ($decisionOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
                @error('decisionStatus')
                    <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div>
            <label for="notes" class="mb-1 block text-sm font-semibold text-slate-700">
                @if ($decisionStatus === \App\Enums\DecisionStatus::REVISION_REQUIRED->value)
                    Catatan Revisi *
                @elseif ($decisionStatus === \App\Enums\DecisionStatus::REJECTED->value)
                    Alasan Penolakan *
                @else
                    Catatan Keputusan &amp; Pertimbangan *
                @endif
            </label>
            <textarea id="notes" wire:model="notes" rows="4"
                placeholder="{{ $decisionStatus === \App\Enums\DecisionStatus::REVISION_REQUIRED->value ? 'Tuliskan bagian yang harus diperbaiki Pemohon...' : ($decisionStatus === \App\Enums\DecisionStatus::REJECTED->value ? 'Tuliskan alasan penolakan RFC...' : 'Masukkan ringkasan pertimbangan, catatan mitigasi risiko, atau instruksi teknis...') }}"
                class="w-full rounded-lg border px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 {{ $errors->has('notes') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('notes')
                <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
            @enderror
        </div>

        @if ($decisionStatus === \App\Enums\DecisionStatus::APPROVED->value)
            <label class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                <input type="checkbox" wire:model="completenessConfirmed"
                    class="mt-0.5 h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                <span>Saya telah memeriksa kelengkapan dokumen dan lampiran</span>
            </label>
            @error('completenessConfirmed')
                <span class="block text-xs text-red-500">{{ $message }}</span>
            @enderror
        @endif

        <div class="flex justify-end border-t border-slate-100 pt-4">
            <button type="submit" wire:loading.attr="disabled"
                class="flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Simpan Keputusan</span>
            </button>
        </div>
    </form>
</div>
