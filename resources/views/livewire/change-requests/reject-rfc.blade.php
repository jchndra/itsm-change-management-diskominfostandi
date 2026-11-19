<x-rfc.action-page :back-href="route('change-requests.show', $rfc->id)" back-label="Kembali ke detail RFC"
    eyebrow="Aksi Change Manager" title="Tolak RFC"
    :description="'Tolak '.$rfc->displayIdentifier().' dan hentikan seluruh pekerjaan yang tersisa'" width="5xl">
    <x-slot:actions>
        <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
    </x-slot:actions>

    @error('status')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
            {{ $message }}</div>
    @enderror

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        {{-- Ringkasan RFC --}}
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 border-b border-slate-100 pb-2 text-lg font-bold text-slate-800">Ringkasan RFC</h2>

                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Nomor RFC</dt>
                        <dd class="mt-0.5 font-bold text-blue-800">{{ $rfc->displayIdentifier() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Judul Perubahan</dt>
                        <dd class="mt-0.5 font-medium leading-snug text-slate-700">{{ $rfc->title }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Aplikasi Terdampak</dt>
                        <dd class="mt-0.5 font-medium text-slate-700">{{ $rfc->application?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pemohon</dt>
                        <dd class="mt-0.5 font-medium text-slate-700">{{ $rfc->submittedBy?->name ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Dampak keputusan --}}
            <section class="rounded-2xl border border-rose-200 bg-rose-50/70 p-6">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M12 9v3.75m0 3.75h.008v.008H12v-.008Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M10.34 3.94 1.82 18.47A1.5 1.5 0 0 0 3.13 21h17.74a1.5 1.5 0 0 0 1.31-2.53L13.66 3.94a1.5 1.5 0 0 0-2.6 0Z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-rose-900">Penolakan bersifat final</h2>
                        <p class="mt-1 text-xs leading-relaxed text-rose-800">
                            RFC akan berstatus <strong>Ditolak</strong> dan tidak dapat dikerjakan, dievaluasi,
                            atau ditutup lagi. Keputusan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                </div>

                @if ($activeTasks->isNotEmpty())
                    <div class="mt-4 rounded-xl border border-rose-200 bg-white/70 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-rose-800">
                            Tugas Tim Rilis yang dibatalkan ({{ $activeTasks->count() }})
                        </p>
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($activeTasks as $task)
                                <li class="flex items-center gap-2 text-xs font-medium text-rose-900">
                                    <svg class="h-3.5 w-3.5 shrink-0 text-rose-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 6l12 12M6 18 18 6" />
                                    </svg>
                                    {{ $task->releaseTeamMember?->name ?? 'Tim Rilis' }}
                                    <span class="font-normal text-rose-700">— {{ $task->execution_status === 'in_progress' ? 'sedang dikerjakan' : 'menunggu dimulai' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>

        {{-- Form penolakan --}}
        <form wire:submit="reject"
            class="flex flex-col space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 lg:col-span-3">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-100 text-sm font-bold text-rose-700 ring-1 ring-inset ring-rose-200">!</span>
                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-slate-800">Alasan Penolakan</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Catat keputusan Change Manager dan alasan yang mendasarinya. Catatan tersimpan pada Riwayat Proses.</p>
                </div>
            </div>

            <div>
                <label for="reason" class="mb-1 block text-sm font-semibold text-slate-700">
                    Alasan penolakan <span class="text-red-500" aria-hidden="true">*</span>
                </label>
                <textarea id="reason" wire:model="reason" rows="6" maxlength="1000" minlength="20"
                    class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm leading-relaxed focus:border-rose-500 focus:ring-2 focus:ring-rose-500"
                    placeholder="Jelaskan pertimbangan penolakan, misalnya hasil evaluasi, perubahan risiko, atau temuan bukti yang tidak memenuhi syarat..."></textarea>
                <p class="mt-1 text-xs text-slate-500">Minimal 20 karakter, maksimal 1.000 karakter.</p>
                @error('reason')
                    <span class="mt-1 block text-xs font-medium text-rose-600" role="alert">{{ $message }}</span>
                @enderror
            </div>

            <label for="confirmed"
                class="flex cursor-pointer items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 transition focus-within:ring-2 focus-within:ring-rose-600 focus-within:ring-offset-2">
                <input id="confirmed" type="checkbox" wire:model="confirmed"
                    class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                <span class="text-sm leading-snug text-slate-700">
                    Saya menyatakan RFC ini <strong>ditolak</strong> dan seluruh pekerjaan tersisa <strong>dihentikan</strong>.
                    <span class="mt-1 block text-xs text-slate-500">
                        Setelah disimpan, tidak ada lagi pengerjaan, evaluasi, atau penutupan untuk RFC ini.
                    </span>
                </span>
            </label>
            @error('confirmed')
                <span class="block text-xs font-medium text-rose-600" role="alert">{{ $message }}</span>
            @enderror

            <div class="mt-auto flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                <a href="{{ route('change-requests.show', $rfc->id) }}" wire:navigate
                    class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
                    Batal
                </a>
                <button type="submit" wire:loading.attr="disabled" wire:target="reject"
                    class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <svg wire:loading wire:target="reject" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                    </svg>
                    <span wire:loading.remove wire:target="reject">Tolak RFC</span>
                    <span wire:loading wire:target="reject">Menolak...</span>
                </button>
            </div>
        </form>
    </div>
</x-rfc.action-page>
