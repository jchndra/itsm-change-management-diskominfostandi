<div class="inline-block">
    @can('cancel', $rfc)
        <button type="button" wire:click="openModal"
            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:border-rose-300 hover:bg-rose-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="m15 9-6 6m0-6 6 6m7-3a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z" />
            </svg>
            Batalkan RFC
        </button>

        @if ($showModal)
            <div class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
                role="dialog" aria-modal="true" aria-labelledby="cancel-rfc-title"
                aria-describedby="cancel-rfc-description" wire:click.self="closeModal"
                wire:keydown.escape.window="closeModal">
                <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-rose-100 text-rose-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="m15 9-6 6m0-6 6 6m7-3a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 id="cancel-rfc-title" class="text-base font-bold text-slate-800">Batalkan RFC?</h3>
                            <p id="cancel-rfc-description" class="mt-0.5 text-xs text-slate-500">
                                RFC tetap tersimpan dengan status Dibatalkan dan tetap tampil di daftar.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4 px-5 py-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Nomor RFC</p>
                            <p class="mt-0.5 text-sm font-bold text-blue-800">{{ $rfc->displayIdentifier() }}</p>
                            <p class="mt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Judul
                                Perubahan</p>
                            <p class="mt-0.5 text-sm font-medium leading-snug text-slate-700">{{ $rfc->title }}</p>
                        </div>

                        <div>
                            <label for="cancellation-reason-{{ $rfc->id }}"
                                class="mb-1 block text-xs font-semibold text-slate-700">
                                Alasan pembatalan <span class="font-normal text-slate-400">(wajib)</span>
                            </label>
                            <textarea id="cancellation-reason-{{ $rfc->id }}" wire:model="reason" rows="3"
                                minlength="10" maxlength="500" autofocus
                                placeholder="Jelaskan alasan RFC ini dibatalkan..."
                                class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100"></textarea>
                            <p class="mt-1 text-[11px] text-slate-400">Minimal 10 dan maksimal 500 karakter. Alasan
                                akan tercatat pada Riwayat Proses.</p>
                            @error('reason')
                                <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3.5">
                        <button type="button" wire:click="closeModal"
                            class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800">
                            Kembali
                        </button>
                        <button type="button" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60">
                            <svg wire:loading wire:target="cancel" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24"
                                fill="none">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3"
                                    stroke-width="3" />
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"
                                    stroke-linecap="round" />
                            </svg>
                            Ya, Batalkan RFC
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endcan
</div>
