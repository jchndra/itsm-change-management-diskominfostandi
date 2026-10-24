<div class="space-y-6">

    @php
        $isStaff = auth()->user()?->can('rfc.view_all') ?? false;
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
    @endphp

    <!-- Header & Tombol Kembali -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ auth()->user()?->isStaff() ? route('change-requests.index') : route('requester.dashboard') }}"
                    class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-slate-900">{{ $rfc->rfc_number ?? 'Draft RFC' }}</h1>

                @php
                    $status = \App\Enums\RfcStatus::fromValue($rfc->status);
                @endphp
                <x-status-badge :status="$status ?? $rfc->status" />
            </div>
            <p class="text-sm text-slate-500 mt-1 ml-12">{{ $rfc->title }}</p>
        </div>

        @if (! $rfc->trashed())
            <div class="flex flex-wrap items-center gap-2">
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

            <!-- Lampiran -->
            @if ($rfc->attachments->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Lampiran</h3>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($rfc->attachments as $attachment)
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
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Riwayat Proses
            </h3>

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
