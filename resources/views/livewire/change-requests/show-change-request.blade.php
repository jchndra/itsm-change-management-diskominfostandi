<div class="space-y-6">

    @php
        $isStaff = auth()->user()?->can('rfc.view_all') ?? false;
        $isReleaseTeam = auth()->user()?->isReleaseTeam() ?? false;
        $returnsToRfcList = $isStaff || $isReleaseTeam;
        $submittedChangeType = match ((string) $rfc->change_type) {
            'standard' => 'Standar',
            'normal' => 'Normal',
            'emergency' => 'Emergency',
            default => null,
        };
        $visibleDecisions = $isStaff
            ? $rfc->decisions
            : $rfc->decisions->filter(fn ($decision) => in_array($decision->decision_status, [
                \App\Enums\DecisionStatus::APPROVED->value,
                \App\Enums\DecisionStatus::REJECTED->value,
            ], true));
        $status = \App\Enums\RfcStatus::fromValue($rfc->status);
        $implementation = $rfc->implementation;
        $implementationEvidences = $implementation
            ? $rfc->evidences->where('evidence_type', 'implementation')->values()
            : collect();
        $canEvaluate = ! $rfc->trashed()
            && (auth()->user()?->can('evaluate', $rfc) ?? false)
            && in_array($status, [\App\Enums\RfcStatus::IMPLEMENTED, \App\Enums\RfcStatus::FAILED], true)
            && $implementation
            && $implementation->evaluated_at === null;
        $canClose = ! $rfc->trashed()
            && (auth()->user()?->can('close', $rfc) ?? false)
            && $implementation
            && $implementation->evaluated_at !== null;
        $awaitingEvaluation = $isStaff
            && in_array($status, [\App\Enums\RfcStatus::IMPLEMENTED, \App\Enums\RfcStatus::FAILED], true)
            && $implementation
            && $implementation->evaluated_at === null;
        $approvalLetters = $rfc->attachments
            ->filter(fn ($attachment) => $attachment->isApprovalLetter())
            ->sortByDesc('id')
            ->values();
        $currentApprovalLetter = $approvalLetters->first();
        $previousApprovalLetters = $approvalLetters->slice(1);
        $supportingAttachments = $rfc->attachments
            ->reject(fn ($attachment) => $attachment->isApprovalLetter())
            ->values();
    @endphp

    <!-- Header & Tombol Kembali -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ $returnsToRfcList ? route('change-requests.index') : route('requester.dashboard') }}"
                    class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-slate-900">{{ $rfc->displayIdentifier() }}</h1>

                <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
            </div>
            <p class="text-sm text-slate-500 mt-1 ml-12">{{ $rfc->title }}</p>
        </div>

        @if (! $rfc->trashed())
            <div class="flex flex-wrap items-center gap-2">
                @if (auth()->user()?->can('assignRelease', $rfc))
                    <a href="{{ route('disposition.assign', $rfc) }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        Disposisi
                    </a>
                @endif

                @if (auth()->user()?->can('implement', $rfc))
                    <a href="{{ route('change-requests.implement', $rfc) }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                        Catat Implementasi
                    </a>
                @endif

                @if ($canEvaluate)
                    <a href="{{ route('change-requests.evaluate', $rfc) }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        Catat Evaluasi
                    </a>
                @endif

                @if ($canClose)
                    <a href="{{ route('change-requests.close', $rfc) }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900">
                        Tutup RFC
                    </a>
                @endif

                @if ($awaitingEvaluation && $canEvaluate)
                    <p class="text-xs font-medium text-amber-700">
                        Tutup RFC aktif setelah evaluasi dicatat
                    </p>
                @endif

                @if (auth()->user()?->can('update', $rfc))
                    <a href="{{ route('change-requests.edit', $rfc) }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        {{ $status === \App\Enums\RfcStatus::NEEDS_REVISION ? 'Revisi RFC' : 'Edit RFC' }}
                    </a>
                @endif

                <livewire:change-requests.cancel-rfc :rfc="$rfc" />
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- Kolom Kiri: Informasi Utama -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Detail Informasi -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Informasi Permintaan Perubahan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 text-sm">
                    <div>
                        <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Judul</span>
                        <p class="text-slate-800 font-medium">{{ $rfc->title }}</p>
                    </div>
                    <div>
                        <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Sistem / Aplikasi Target</span>
                        <p class="text-slate-800 font-medium">{{ $rfc->application?->name }}</p>
                    </div>
                    @if (filled($submittedChangeType))
                        <div>
                            <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Kategori Perubahan</span>
                            <p class="text-slate-800 font-medium">{{ $submittedChangeType }}</p>
                        </div>
                    @endif
                    @if (! $isStaff && filled($rfc->ticket_number))
                        <div>
                            <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Nomor Tiket</span>
                            <p class="text-slate-800 font-medium">{{ $rfc->ticket_number }}</p>
                        </div>
                    @endif
                    <div>
                        <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Target Tanggal Pelaksanaan</span>
                        <p class="text-slate-800 font-medium">
                            {{ $rfc->target_implementation_date?->copy()->locale('id')->translatedFormat('d M Y') }}
                        </p>
                    </div>
                    <div>
                        <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Tanggal Diajukan</span>
                        <p class="text-slate-800 font-medium">
                            {{ $rfc->created_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                            @if ($rfc->created_at)
                                <span class="text-slate-500 font-normal">&middot;
                                    {{ $rfc->created_at->copy()->locale('id')->diffForHumans() }}</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Nota Dinas</span>
                        @if ($rfc->is_physical_letter)
                            <p class="text-slate-800 font-medium">
                                Ya
                                @if (filled($rfc->physical_letter_number))
                                    <span class="block text-slate-600">{{ $rfc->physical_letter_number }}</span>
                                @endif
                            </p>
                        @else
                            <p class="text-slate-800 font-medium">Tidak</p>
                        @endif
                    </div>
                    @if ($isStaff)
                        <div>
                            <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Nama Pemohon</span>
                            <p class="text-slate-800 font-medium">{{ $rfc->submittedBy?->name }}</p>
                        </div>
                        @if ($rfc->submittedBy?->opd)
                            <div>
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Unit Kerja</span>
                                <p class="text-slate-800 font-medium">{{ $rfc->submittedBy->opd->name }}</p>
                            </div>
                        @endif
                    @endif
                    @if (filled($rfc->description))
                        <div class="md:col-span-2">
                            <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Deskripsi Perubahan</span>
                            <p class="text-slate-800 whitespace-pre-wrap">{{ $rfc->description }}</p>
                        </div>
                    @endif
                    <div class="md:col-span-2">
                        <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Alasan Perubahan</span>
                        <p class="text-slate-800 whitespace-pre-wrap">{{ $rfc->reason_for_change }}</p>
                    </div>
                    @if (filled($rfc->impact_description))
                        <div class="md:col-span-2">
                            <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Estimasi Dampak</span>
                            <p class="text-slate-800 whitespace-pre-wrap">{{ $rfc->impact_description }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Surat Persetujuan Bertanda Tangan --}}
            @if ($approvalLetters->isNotEmpty() || (auth()->user()?->can('update', $rfc) ?? false))
                <div class="bg-white rounded-xl shadow-sm border border-indigo-100 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Surat Persetujuan Bertanda Tangan</h3>

                    @if ($currentApprovalLetter)
                        <div class="rounded-lg border border-indigo-200 bg-indigo-50/60 px-4 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                                        <span class="min-w-0 truncate">{{ $currentApprovalLetter->original_name }}</span>
                                        <span
                                            class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                            Berlaku
                                        </span>
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ number_format($currentApprovalLetter->size / 1024, 0, ',', '.') }} KB
                                        &middot;
                                        {{ $currentApprovalLetter->created_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 text-xs font-semibold">
                                    <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $currentApprovalLetter]) }}"
                                        target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-700">
                                        Lihat
                                    </a>
                                    <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $currentApprovalLetter, 'download' => 1]) }}"
                                        class="text-indigo-600 hover:text-indigo-700">
                                        Unduh
                                    </a>
                                </div>
                            </div>

                            @if ($previousApprovalLetters->isNotEmpty())
                                <div class="mt-3 border-t border-indigo-100 pt-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                        Riwayat surat persetujuan ({{ $previousApprovalLetters->count() }})
                                    </p>
                                    <ul class="mt-2 divide-y divide-indigo-100">
                                        @foreach ($previousApprovalLetters as $previousApprovalLetter)
                                            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-xs"
                                                wire:key="approval-letter-{{ $previousApprovalLetter->id }}">
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate font-medium text-slate-700">
                                                        {{ $previousApprovalLetter->original_name }}
                                                    </p>
                                                    <p class="text-[11px] text-slate-400">
                                                        {{ $previousApprovalLetter->created_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                                    </p>
                                                </div>
                                                <div class="flex items-center gap-3 font-semibold">
                                                    <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $previousApprovalLetter]) }}"
                                                        target="_blank" rel="noopener"
                                                        class="text-indigo-600 hover:text-indigo-700">
                                                        Lihat
                                                    </a>
                                                    <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $previousApprovalLetter, 'download' => 1]) }}"
                                                        class="text-indigo-600 hover:text-indigo-700">
                                                        Unduh
                                                    </a>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
                            Belum ada surat persetujuan bertanda tangan. Unggah PDF melalui halaman edit sebelum
                            mengajukan RFC.
                        </p>
                    @endif
                </div>
            @endif

            @if ($isStaff && $rfc->releaseTasks->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Disposisi</h3>

                    <div class="space-y-4">
                        @foreach ($rfc->releaseTasks as $task)
                            <div class="rounded-lg border border-blue-100 bg-blue-50/50 p-4"
                                wire:key="show-disposition-task-{{ $task->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ $task->releaseTeamMember?->name ?? 'Anggota Tim Rilis' }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            Diberikan oleh {{ $task->assignedBy?->name ?? '-' }}
                                        </p>
                                    </div>
                                    <span class="text-[11px] text-slate-500">
                                        {{ $task->created_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                    </span>
                                </div>
                                <div class="mt-3">
                                    <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Instruksi</span>
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ $task->instruction }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Lampiran Pendukung --}}
            @if ($supportingAttachments->isNotEmpty())                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Lampiran Pendukung</h3>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($supportingAttachments as $attachment)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-800">{{ $attachment->original_name }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ number_format($attachment->size / 1024, 0, ',', '.') }} KB
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
                </div>
            @endif

            @if ($implementation)
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Hasil Implementasi</h3>

                    @if ($isStaff)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 text-sm">
                            <div>
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Hasil</span>
                                <p class="text-slate-800 font-medium">
                                    {{ \App\Enums\ImplementationResult::labelFor($implementation->implementation_result) }}
                                </p>
                            </div>
                            <div>
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Pelaksana Perubahan</span>
                                <p class="text-slate-800 font-medium">{{ $implementation->executor_name }}</p>
                            </div>
                            <div>
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Waktu Mulai</span>
                                <p class="text-slate-800 font-medium">
                                    {{ $implementation->started_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                </p>
                            </div>
                            <div>
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Waktu Selesai</span>
                                <p class="text-slate-800 font-medium">
                                    {{ $implementation->finished_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                </p>
                            </div>
                            <div class="md:col-span-2">
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Sumber Laporan</span>
                                <p class="text-slate-800 font-medium">{{ $implementation->report_source ?? '-' }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Ringkasan Hasil</span>
                                <p class="text-slate-800 whitespace-pre-wrap">{{ $implementation->summary }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Hasil Pengujian</span>
                                <p class="text-slate-800 whitespace-pre-wrap">{{ $implementation->test_results }}</p>
                            </div>
                            @if (filled($implementation->failure_notes))
                                <div class="md:col-span-2">
                                    <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Catatan Kegagalan / Rollback</span>
                                    <p class="text-slate-800 whitespace-pre-wrap">{{ $implementation->failure_notes }}</p>
                                </div>
                            @endif
                            <div>
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Tindak Lanjut</span>
                                <p class="text-slate-800 font-medium">
                                    {{ filled($implementation->follow_up_action) ? \App\Enums\FollowUpAction::labelFor($implementation->follow_up_action) : '-' }}
                                </p>
                            </div>
                            @if (filled($implementation->follow_up_notes))
                                <div>
                                    <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Keterangan Tindak Lanjut</span>
                                    <p class="text-slate-800 whitespace-pre-wrap">{{ $implementation->follow_up_notes }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="mt-6 border-t border-slate-100 pt-4">
                            <h4 class="text-sm font-bold text-slate-700 mb-3">
                                Bukti Pendukung ({{ $implementationEvidences->count() }} file)
                            </h4>

                            @if ($implementationEvidences->isEmpty())
                                <p class="text-sm text-slate-500">Tidak ada berkas bukti.</p>
                            @else
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($implementationEvidences as $evidence)
                                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-slate-800">{{ $evidence->original_filename }}</p>
                                                <p class="text-xs text-slate-400">
                                                    {{ number_format($evidence->file_size / 1024, 0, ',', '.') }} KB
                                                    &middot; {{ $evidence->mime_type }}
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-3 text-xs font-semibold">
                                                <a href="{{ route('change-requests.evidences.show', ['rfc' => $rfc, 'evidence' => $evidence]) }}"
                                                    target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-700">
                                                    Lihat
                                                </a>
                                                <a href="{{ route('change-requests.evidences.show', ['rfc' => $rfc, 'evidence' => $evidence, 'download' => 1]) }}"
                                                    class="text-indigo-600 hover:text-indigo-700">
                                                    Unduh
                                                </a>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 text-sm">
                            <div>
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Hasil</span>
                                <p class="text-slate-800 font-medium">
                                    {{ \App\Enums\ImplementationResult::labelFor($implementation->implementation_result) }}
                                </p>
                            </div>
                            <div>
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Waktu Pelaksanaan</span>
                                <p class="text-slate-800 font-medium">
                                    {{ $implementation->started_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                    s.d.
                                    {{ $implementation->finished_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                </p>
                            </div>
                            <div class="md:col-span-2">
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Ringkasan Hasil</span>
                                <p class="text-slate-800 whitespace-pre-wrap">{{ $implementation->summary }}</p>
                            </div>
                            @if (filled($implementation->failure_notes))
                                <div class="md:col-span-2">
                                    <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Catatan Kegagalan / Rollback</span>
                                    <p class="text-slate-800 whitespace-pre-wrap">{{ $implementation->failure_notes }}</p>
                                </div>
                            @endif
                            <div>
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Tindak Lanjut</span>
                                <p class="text-slate-800 font-medium">
                                    {{ filled($implementation->follow_up_action) ? \App\Enums\FollowUpAction::labelFor($implementation->follow_up_action) : '-' }}
                                </p>
                            </div>
                            @if (filled($implementation->follow_up_notes))
                                <div>
                                    <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Keterangan Tindak Lanjut</span>
                                    <p class="text-slate-800 whitespace-pre-wrap">{{ $implementation->follow_up_notes }}</p>
                                </div>
                            @endif
                            <div class="md:col-span-2">
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Bukti Pendukung</span>
                                <p class="text-slate-800 font-medium">Bukti pendukung: {{ $implementationEvidences->count() }} file</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            @if ($isStaff && ($implementation?->evaluated_at || $rfc->closed_at || filled($rfc->closure_notes)))
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Evaluasi dan Penutupan</h3>

                    <div class="space-y-5 text-sm">
                        @if ($implementation?->evaluated_at)
                            <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <h4 class="text-sm font-bold text-emerald-900">Catatan Evaluasi</h4>
                                    <span class="text-[11px] text-emerald-800">
                                        {{ $implementation->evaluated_at->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                    </span>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-emerald-950">{{ $implementation->evaluation_notes }}</p>
                                <p class="mt-3 text-xs text-emerald-800">
                                    Dievaluasi oleh {{ $implementation->evaluatedBy?->name ?? 'Change Manager' }}
                                </p>
                            </div>
                        @endif

                        @if ($rfc->closed_at || filled($rfc->closure_notes))
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <h4 class="text-sm font-bold text-slate-800">Catatan Penutupan</h4>
                                    @if ($rfc->closed_at)
                                        <span class="text-[11px] text-slate-500">
                                            {{ $rfc->closed_at->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-slate-700">{{ $rfc->closure_notes ?: '-' }}</p>
                                @if ($rfc->closedBy || $rfc->closed_at)
                                    <p class="mt-3 text-xs text-slate-500">
                                        Ditutup oleh {{ $rfc->closedBy?->name ?? '-' }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Ditetapkan Petugas --}}
            @php
                // Nilai di blok ini adalah hasil penetapan staf. Untuk Pemohon,
                // nomor tiket tampil di Informasi Permintaan Perubahan tanpa
                // membocorkan prioritas, kategori layanan, atau catatan internal.
                $officerTicketNumber = $isStaff ? ($rfc->ticket_number ?? null) : null;
                $officerCategory = null;
                $officerPriority = null;

                if ($isStaff && filled($rfc->category)) {
                    $category = $rfc->category instanceof \App\Enums\ServiceCategory
                        ? $rfc->category
                        : \App\Enums\ServiceCategory::tryFrom((string) $rfc->category);
                    $officerCategory = $category?->label() ?? (string) $rfc->category;
                }

                if ($isStaff && filled($rfc->priority)) {
                    $priority = $rfc->priority instanceof \App\Enums\TicketPriority
                        ? $rfc->priority
                        : \App\Enums\TicketPriority::tryFrom((string) $rfc->priority);
                    $officerPriority = $priority?->label() ?? (string) $rfc->priority;
                }

                $officerAdminNote = $isStaff ? ($rfc->admin_note ?? null) : null;
                $officerSubmittedChangeType = $isStaff && filled($rfc->change_type)
                    ? ($submittedChangeType ?: (string) $rfc->change_type)
                    : null;
                $officerFinalClassification = $isStaff ? $rfc->riskAssessment?->classification : null;
                $submittedClassification = match ((string) $rfc->change_type) {
                    'standard' => 'Standard Change',
                    'normal' => 'Normal Change',
                    'emergency' => 'Emergency Change',
                    default => null,
                };
                $showSubmittedChangeType = filled($officerSubmittedChangeType)
                    && (blank($officerFinalClassification) || $submittedClassification !== $officerFinalClassification);
            @endphp
            @if (filled($officerTicketNumber) || filled($officerCategory) || filled($officerPriority) || filled($officerSubmittedChangeType) || filled($officerFinalClassification) || filled($officerAdminNote))
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Ditetapkan Petugas</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 text-sm">
                        @if (filled($officerTicketNumber))
                            <div>
                                <span
                                    class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Nomor Tiket</span>
                                <p class="text-slate-800 font-medium">{{ $officerTicketNumber }}</p>
                            </div>
                        @endif
                        @if (filled($officerCategory))
                            <div>
                                <span
                                    class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Kategori Layanan</span>
                                <p class="text-slate-800 font-medium">{{ $officerCategory }}</p>
                            </div>
                        @endif
                        @if (filled($officerPriority))
                            <div>
                                <span
                                    class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Prioritas</span>
                                <p class="text-slate-800 font-medium">{{ $officerPriority }}</p>
                            </div>
                        @endif
                        @if ($showSubmittedChangeType)
                            <div>
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Kategori diusulkan Pemohon</span>
                                <p class="text-slate-800 font-medium">{{ $officerSubmittedChangeType }}</p>
                            </div>
                        @endif
                        @if (filled($officerFinalClassification))
                            <div>
                                <span class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Klasifikasi ditetapkan Change Manager</span>
                                <p class="text-slate-800 font-medium">{{ $officerFinalClassification }}</p>
                            </div>
                        @endif
                        @if (filled($officerAdminNote))
                            <div class="md:col-span-2">
                                <span
                                    class="block text-slate-500 text-xs uppercase tracking-wider font-semibold mb-1">Catatan Admin untuk Change Manager</span>
                                <p class="text-slate-800 font-medium whitespace-pre-wrap">{{ $officerAdminNote }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Alasan Penolakan / Permintaan Revisi -->
            @php
                $isRejected = $status === \App\Enums\RfcStatus::REJECTED;
                $needsRevision = $status === \App\Enums\RfcStatus::NEEDS_REVISION;
                $finalRejection = $rfc->decisions
                    ->where('decision_status', \App\Enums\DecisionStatus::REJECTED->value)
                    ->last();
                $rejectionReason = $isRejected ? ($rfc->rejection_note ?: $finalRejection?->reason) : null;
                $revisionReason = $needsRevision ? $rfc->rejection_note : null;
            @endphp
            @if (filled($rejectionReason))
                <div class="bg-rose-50 rounded-xl border border-rose-100 p-6">
                    <h3 class="text-lg font-bold text-rose-800 mb-2">Alasan Penolakan</h3>
                    <p class="text-sm text-rose-900 whitespace-pre-wrap">{{ $rejectionReason }}</p>
                </div>
            @endif
            @if (filled($revisionReason))
                <div class="bg-amber-50 rounded-xl border border-amber-100 p-6">
                    <h3 class="text-lg font-bold text-amber-800 mb-2">Alasan Permintaan Revisi</h3>
                    <p class="text-sm text-amber-900 whitespace-pre-wrap">{{ $revisionReason }}</p>
                </div>
            @endif

            @php
                $cancellationReason = $status === \App\Enums\RfcStatus::CANCELLED
                    ? app(\App\Services\RfcHistoryService::class)->cancellationReason($rfc)
                    : null;
            @endphp
            @if (filled($cancellationReason))
                <div class="bg-slate-50 rounded-xl border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Alasan Pembatalan</h3>
                    <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ $cancellationReason }}</p>
                </div>
            @endif

            <!-- Panel Keputusan -->
            @if (($isStaff && $rfc->riskAssessment) || $visibleDecisions->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Hasil Evaluasi & Keputusan</h3>

                    @if ($isStaff && $rfc->riskAssessment)
                        <div class="mb-6">
                            <h4 class="text-sm font-bold text-slate-700 mb-3">Penilaian Risiko (Change Manager)</h4>
                            <div class="flex flex-wrap gap-4">
                                <div class="p-3 bg-slate-50 rounded-lg border border-slate-100 flex-1 min-w-[120px]">
                                    <span class="block text-xs text-slate-500 mb-1">Tingkat Risiko</span>
                                    <span
                                        class="font-bold text-slate-800">{{ $rfc->riskAssessment->risk_level }}</span>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-lg border border-slate-100 flex-1 min-w-[120px]">
                                    <span class="block text-xs text-slate-500 mb-1">Dampak</span>
                                    <span
                                        class="font-bold text-slate-800">{{ $rfc->riskAssessment->impact_level }}</span>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-lg border border-slate-100 flex-1 min-w-[120px]">
                                    <span class="block text-xs text-slate-500 mb-1">Klasifikasi</span>
                                    <span
                                        class="font-bold text-blue-700">{{ $rfc->riskAssessment->classification }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($visibleDecisions->isNotEmpty())
                        <div>
                            <h4 class="text-sm font-bold text-slate-700 mb-3">Keputusan Change Manager</h4>
                            @foreach ($visibleDecisions as $decision)
                                @php
                                    $decisionCard = match ($decision->decision_status) {
                                        \App\Enums\DecisionStatus::APPROVED->value => 'bg-green-50 border-green-100',
                                        \App\Enums\DecisionStatus::REJECTED->value => 'bg-rose-50 border-rose-100',
                                        \App\Enums\DecisionStatus::RETURNED_TO_ADMIN->value => 'bg-amber-50 border-amber-100',
                                        default => 'bg-slate-50 border-slate-200',
                                    };
                                    $decisionBadge = match ($decision->decision_status) {
                                        \App\Enums\DecisionStatus::APPROVED->value => 'bg-green-200 text-green-800',
                                        \App\Enums\DecisionStatus::REJECTED->value => 'bg-rose-200 text-rose-800',
                                        \App\Enums\DecisionStatus::RETURNED_TO_ADMIN->value => 'bg-amber-200 text-amber-800',
                                        default => 'bg-slate-200 text-slate-700',
                                    };
                                @endphp
                                <div class="p-4 rounded-lg border {{ $decisionCard }}">
                                    <div class="flex justify-between items-start gap-3 mb-2">
                                        @if ($isStaff)
                                            <div>
                                                <span
                                                    class="font-bold text-slate-800">{{ $decision->snapshot_authority_name ?? 'Change Manager' }}</span>
                                                @if (filled($decision->snapshot_authority_position))
                                                    <span
                                                        class="block text-xs text-slate-500">{{ $decision->snapshot_authority_position }}</span>
                                                @endif
                                            </div>
                                        @endif
                                        <span class="ml-auto text-xs font-bold px-2 py-1 rounded {{ $decisionBadge }}">
                                            {{ \App\Enums\DecisionStatus::labelFor($decision->decision_status) }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-slate-700 mt-2"><span
                                            class="font-semibold">Catatan/Alasan:</span>
                                        {{ $decision->reason ?? '-' }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Kolom Kanan: Timeline Riwayat -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6"
            wire:poll.30s="refreshProcessHistory"
            aria-label="Riwayat proses, diperbarui otomatis setiap 30 detik">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Riwayat Proses
                </h3>

                <span title="Diperbarui otomatis setiap 30 detik"
                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">
                    <span class="relative flex h-1.5 w-1.5" aria-hidden="true">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-70"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    </span>
                    Otomatis
                </span>
            </div>

            @php
                $history = app(\App\Services\RfcHistoryService::class)->build($rfc, $isStaff);
                $events = $history['events'];
                $nextSteps = $history['next_steps'];
            @endphp

            @if (empty($events))
                <p class="text-sm text-slate-500">Belum ada riwayat proses untuk RFC ini.</p>
            @else
                <div
                    class="relative pl-6 space-y-6 before:absolute before:inset-0 before:ml-[11px] before:-translate-x-px before:h-full before:w-0.5 before:bg-slate-200">
                    @foreach ($events as $event)
                        @php
                            $tone = $event['tone'] ?? 'neutral';
                            $circle = match ($tone) {
                                'success' => 'bg-emerald-500 text-white ring-emerald-50',
                                'danger' => 'bg-rose-500 text-white ring-rose-50',
                                'warning' => 'bg-amber-500 text-white ring-amber-50',
                                'info' => 'bg-blue-500 text-white ring-blue-50',
                                default => 'bg-slate-300 text-white ring-slate-50',
                            };
                        @endphp

                        <div class="relative flex items-start">
                            <div
                                class="absolute left-[-29px] flex items-center justify-center w-6 h-6 rounded-full {{ $circle }} shadow-sm ring-4 ring-white">
                                @if ($tone === 'success')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                            d="M5 13l4 4L19 7"></path>
                                    </svg>
                                @elseif ($tone === 'danger')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                @elseif ($tone === 'warning')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"
                                            d="M12 9v4m0 4h.01"></path>
                                    </svg>
                                @else
                                    <span class="h-2 w-2 rounded-full bg-white"></span>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-start justify-between gap-1">
                                    <h4 class="text-sm font-bold text-slate-800">{{ $event['label'] }}</h4>
                                    @if ($event['time'])
                                        <time class="text-[11px] text-slate-400 whitespace-nowrap">
                                            {{ $event['time']->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                        </time>
                                    @endif
                                </div>

                                @if (filled($event['actor_name']))
                                    <p class="text-[11px] text-slate-500 mt-1">
                                        Oleh {{ $event['actor_name'] }}
                                        @if (filled($event['actor_role']))
                                            &middot; {{ $event['actor_role'] }}
                                        @endif
                                    </p>
                                @endif

                                @if (filled($event['note']))
                                    <p class="mt-1 text-xs text-slate-600 whitespace-pre-wrap">{{ $event['note'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (count($nextSteps) > 0)
                <div class="mt-6 border-t border-slate-100 pt-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-3">Langkah Berikutnya</p>
                    <div
                        class="relative pl-6 space-y-4 before:absolute before:inset-0 before:ml-[11px] before:-translate-x-px before:h-full before:w-0.5 before:bg-slate-100">
                        @foreach ($nextSteps as $step)
                            <div class="relative flex items-center">
                                <span
                                    class="absolute left-[-29px] flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 ring-4 ring-white">
                                    <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                                </span>
                                <p class="text-xs font-medium text-slate-400">{{ $step['label'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
