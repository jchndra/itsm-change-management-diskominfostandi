<div class="mx-auto max-w-5xl space-y-6">
    @php
        $categoryLabel = match ((string) $rfc->change_type) {
            'standard' => 'Standar',
            'normal' => 'Normal',
            'emergency' => 'Darurat (Emergency)',
            default => filled($rfc->change_type) ? ucfirst((string) $rfc->change_type) : '-',
        };
        $risk = $rfc->riskAssessment;
        $decisionLabels = [
            \App\Enums\DecisionStatus::APPROVED->value => 'Disetujui',
            \App\Enums\DecisionStatus::REJECTED->value => 'Ditolak',
            \App\Enums\DecisionStatus::RETURNED_TO_ADMIN->value => 'Dikembalikan ke Admin',
        ];
    @endphp

    <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('disposition.index') }}" wire:navigate
                    class="rounded-full bg-slate-100 p-2 text-slate-600 transition hover:bg-slate-200"
                    aria-label="Kembali ke daftar disposisi">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0 7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Disposisi RFC</h1>
                    <p class="mt-0.5 text-sm text-slate-500">
                        {{ $rfc->displayIdentifier() }} &middot; {{ $rfc->title }}
                    </p>
                </div>
            </div>
        </div>

        <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
    </div>

    @error('disposition')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            {{ $message }}
        </div>
    @enderror

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 border-b border-slate-100 pb-2 text-lg font-bold text-slate-800">Ringkasan RFC</h2>

        <div class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm md:grid-cols-2">
            <div>
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Judul</span>
                <p class="font-medium text-slate-800">{{ $rfc->title }}</p>
            </div>
            <div>
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Aplikasi</span>
                <p class="font-medium text-slate-800">{{ $rfc->application?->name ?? '-' }}</p>
            </div>
            <div>
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Pemohon</span>
                <p class="font-medium text-slate-800">{{ $rfc->submittedBy?->name ?? '-' }}</p>
                <p class="text-xs text-slate-500">{{ $rfc->submittedBy?->opd?->name ?? 'Unit kerja belum diisi' }}</p>
            </div>
            <div>
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Kategori Diusulkan</span>
                <p class="font-medium text-slate-800">{{ $categoryLabel }}</p>
            </div>
            <div>
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Target Tanggal Pemohon</span>
                <p class="font-medium text-slate-800">
                    {{ $rfc->target_implementation_date?->copy()->locale('id')->translatedFormat('d M Y') ?? '-' }}
                </p>
            </div>
            <div>
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Kategori Layanan</span>
                <p class="font-medium text-slate-800">
                    {{ $rfc->category instanceof \App\Enums\ServiceCategory ? $rfc->category->label() : ($rfc->category ? \App\Enums\ServiceCategory::tryFrom((string) $rfc->category)?->label() ?? (string) $rfc->category : '-') }}
                </p>
            </div>
            <div class="md:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Deskripsi Perubahan</span>
                <p class="whitespace-pre-wrap text-slate-700">{{ $rfc->description ?: '-' }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 border-b border-slate-100 pb-2 text-lg font-bold text-slate-800">Hasil Analisis Risiko &amp; Keputusan</h2>

        @if ($risk)
            <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
                <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                    <span class="block text-xs text-slate-500">Tingkat Risiko</span>
                    <p class="mt-1 font-bold text-slate-800">{{ $risk->risk_level ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                    <span class="block text-xs text-slate-500">Dampak</span>
                    <p class="mt-1 font-bold text-slate-800">{{ $risk->impact_level ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                    <span class="block text-xs text-slate-500">Klasifikasi</span>
                    <p class="mt-1 font-bold text-blue-700">{{ $risk->classification ?? '-' }}</p>
                </div>
            </div>

            @if (filled($risk->mitigation_plan) || filled($risk->rollback_plan) || filled($risk->analysis_notes ?? null))
                <dl class="mt-4 space-y-3 text-sm">
                    @if (filled($risk->analysis_notes ?? null))
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Catatan Analisis</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-700">{{ $risk->analysis_notes }}</dd>
                        </div>
                    @endif
                    @if (filled($risk->mitigation_plan))
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rencana Mitigasi</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-700">{{ $risk->mitigation_plan }}</dd>
                        </div>
                    @endif
                    @if (filled($risk->rollback_plan))
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rencana Rollback</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-700">{{ $risk->rollback_plan }}</dd>
                        </div>
                    @endif
                </dl>
            @endif
        @else
            <p class="text-sm text-slate-500">Belum ada analisis risiko untuk RFC ini.</p>
        @endif

        <div class="mt-5 border-t border-slate-100 pt-4">
            <h3 class="mb-3 text-sm font-bold text-slate-700">Keputusan Change Manager</h3>
            @forelse ($rfc->decisions as $decision)
                <div class="mb-2 rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-semibold text-slate-800">
                            {{ $decisionLabels[$decision->decision_status] ?? \App\Enums\DecisionStatus::labelFor($decision->decision_status) }}
                        </span>
                        <span class="text-xs text-slate-500">
                            {{ filled($decision->decision_date) ? \Illuminate\Support\Carbon::parse($decision->decision_date)->locale('id')->translatedFormat('d M Y, H:i') : '-' }}
                        </span>
                    </div>
                    <p class="mt-1 whitespace-pre-wrap text-slate-600">{{ $decision->reason ?: '-' }}</p>
                    @if (filled($decision->snapshot_authority_name))
                        <p class="mt-1 text-xs text-slate-500">Oleh {{ $decision->snapshot_authority_name }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">Belum ada keputusan tercatat.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 border-b border-slate-100 pb-2 text-lg font-bold text-slate-800">Lampiran</h2>

        @if ($rfc->attachments->isEmpty())
            <p class="text-sm text-slate-500">RFC ini tidak memiliki lampiran.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($rfc->attachments as $attachment)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        wire:key="disposition-attachment-{{ $attachment->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $attachment->original_name }}</p>
                            <p class="text-xs text-slate-400">
                                {{ $attachment->isApprovalLetter() ? 'Surat persetujuan' : 'Lampiran pendukung' }}
                                &middot; {{ number_format($attachment->size / 1024, 0, ',', '.') }} KB
                            </p>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-semibold">
                            <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]) }}"
                                target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-700">
                                Lihat
                            </a>
                            <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment, 'download' => 1]) }}"
                                class="text-indigo-600 hover:text-indigo-700">
                                Unduh
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <form wire:submit="assign" class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div>
            <h2 class="text-lg font-bold text-slate-800">Penerima Disposisi</h2>
            <p class="mt-1 text-sm text-slate-500">
                Pilih anggota Tim Rilis yang akan menerima tugas. Hanya anggota aktif yang tertaut ke akun aktif
                dengan role Tim Rilis yang dapat dipilih.
            </p>
        </div>

        @if ($eligibleMembers->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Belum ada anggota Tim Rilis yang memenuhi syarat. Pastikan anggota aktif sudah tertaut ke akun aktif
                dengan role Tim Rilis.
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($eligibleMembers as $member)
                    <label wire:key="eligible-member-{{ $member->id }}"
                        class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-blue-300 hover:bg-blue-50/40">
                        <input type="checkbox" wire:model="selectedMemberIds" value="{{ $member->id }}"
                            class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="min-w-0">
                            <span class="block font-semibold text-slate-800">{{ $member->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $member->role_title }}</span>
                            <span class="mt-1 block text-xs text-slate-500">
                                {{ $member->user?->name }} &middot; {{ $member->user?->email }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('selectedMemberIds')
                <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
            @error('selectedMemberIds.*')
                <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        @endif

        <div>
            <label for="instruction" class="mb-1 block text-sm font-semibold text-slate-700">
                Instruksi <span class="text-rose-500">*</span>
            </label>
            <textarea id="instruction" wire:model="instruction" rows="5" maxlength="1000"
                class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                placeholder="Tuliskan instruksi pelaksanaan yang harus dipahami Tim Rilis..."></textarea>
            <p class="mt-1 text-xs text-slate-500">Minimal 10 karakter, maksimal 1.000 karakter.</p>
            @error('instruction')
                <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <label class="flex cursor-pointer items-start gap-3 text-sm text-amber-900">
                <input type="checkbox" wire:model="confirmed"
                    class="mt-0.5 h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                <span>
                    Saya menyatakan RFC ini siap diteruskan ke Tim Rilis; disposisi tidak dapat diubah.
                </span>
            </label>
            @error('confirmed')
                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <a href="{{ route('disposition.index') }}" wire:navigate
                class="rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
                Batal
            </a>
            <button type="submit" wire:loading.attr="disabled" wire:target="assign"
                @disabled($eligibleMembers->isEmpty())
                class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                <span wire:loading.remove wire:target="assign">Kirim Disposisi</span>
                <span wire:loading wire:target="assign">Mengirim...</span>
            </button>
        </div>
    </form>
</div>
