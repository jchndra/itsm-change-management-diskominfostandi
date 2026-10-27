<div class="{{ $variant === 'menu' ? 'block' : 'inline-block' }}">
    @if ($variant === 'menu')
        <button type="button" wire:click="openModal"
            class="block w-full px-3.5 py-2 text-left text-xs font-medium text-rose-600 transition hover:bg-rose-50 hover:text-rose-700">
            Hapus RFC
        </button>
    @else
        <button type="button" wire:click="openModal"
            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:border-rose-300 hover:bg-rose-100">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v5M14 11v5" />
            </svg>
            Hapus
        </button>
    @endif

    @if ($showModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
            role="dialog" aria-modal="true" aria-labelledby="delete-rfc-title"
            aria-describedby="delete-rfc-description" wire:click.self="closeModal"
            wire:keydown.escape.window="closeModal">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-4">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-rose-100 text-rose-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v5M14 11v5" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 id="delete-rfc-title" class="text-base font-bold text-slate-800">Hapus RFC?</h3>
                        <p id="delete-rfc-description" class="mt-0.5 text-xs text-slate-500">Tindakan ini tidak
                            menghapus data secara permanen.</p>
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
                        <label for="deletion-reason-{{ $rfc->id }}"
                            class="mb-1 block text-xs font-semibold text-slate-700">
                            Alasan penghapusan <span class="font-normal text-slate-400">(opsional)</span>
                        </label>
                        <textarea id="deletion-reason-{{ $rfc->id }}" wire:model="reason" rows="3" maxlength="1000"
                            autofocus
                            placeholder="Contoh: draft dibuat untuk pengujian dan tidak akan diajukan."
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100"></textarea>
                        <p class="mt-1 text-[11px] text-slate-400">Alasan akan tercatat pada Riwayat Penghapusan
                            untuk kebutuhan audit.</p>
                        @error('reason')
                            <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($errorMessage)
                        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
                            {{ $errorMessage }}
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3.5">
                    <button type="button" wire:click="closeModal"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800">
                        Batal
                    </button>
                    <button type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <svg wire:loading wire:target="delete" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24"
                            fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3"
                                stroke-width="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"
                                stroke-linecap="round" />
                        </svg>
                        Hapus RFC
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
