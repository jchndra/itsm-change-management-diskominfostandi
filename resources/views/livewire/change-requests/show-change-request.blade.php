<div class="space-y-6"
    x-data="{
        attachmentViewerOpen: false,
        attachmentViewerUrl: '',
        attachmentViewerDownloadUrl: '',
        attachmentViewerTitle: '',
        attachmentViewerType: 'pdf',
        openAttachmentViewer(url, downloadUrl, title, type) {
            const viewerType = type || 'pdf';
            this.attachmentViewerUrl = url;
            this.attachmentViewerDownloadUrl = downloadUrl
                || (viewerType === 'image'
                    ? url
                    : url + (url.includes('?') ? '&' : '?') + 'download=1');
            this.attachmentViewerTitle = title || 'Lampiran';
            this.attachmentViewerType = viewerType;
            this.attachmentViewerOpen = true;
        },
        closeAttachmentViewer() {
            this.attachmentViewerOpen = false;
            this.attachmentViewerUrl = '';
            this.attachmentViewerDownloadUrl = '';
            this.attachmentViewerTitle = '';
            this.attachmentViewerType = 'pdf';
        },
    }"
    x-on:keydown.escape.window="closeAttachmentViewer()"
    x-on:open-attachment-viewer.window="openAttachmentViewer($event.detail.url, $event.detail.downloadUrl ?? '', $event.detail.title ?? 'Lampiran', $event.detail.type ?? 'pdf')"
    x-effect="document.body.style.overflow = attachmentViewerOpen ? 'hidden' : ''">

    @php
        $isStaff = auth()->user()?->can('rfc.view_all') ?? false;
        $isReleaseTeam = auth()->user()?->isReleaseTeam() ?? false;
        $isReleaseTeamView = $isReleaseTeam && ! $isStaff;
        $isRequesterOwner = auth()->id() === $rfc->submitted_by_id;
        $returnsToRfcList = $isStaff || $isReleaseTeam;
        $releaseTeamDispositionTask = $isReleaseTeamView && $rfc->relationLoaded('releaseTasks')
            ? $rfc->releaseTasks->first(
                fn ($task) => $task->releaseTeamMember?->user_id === auth()->id()
            )
            : null;
        $status = \App\Enums\RfcStatus::fromValue($rfc->status);
        $submittedChangeType = match ((string) $rfc->change_type) {
            'standard' => 'Standar',
            'normal' => 'Normal',
            'emergency' => 'Emergency',
            default => null,
        };
        $targetDateIndicator = $rfc->targetDateIndicator();
        $runningDays = $rfc->runningDays();
        $currentPosition = $rfc->currentPosition();
        $finalClassification = $rfc->riskAssessment?->classification;
        $emergencyBox = null;

        if ((string) $rfc->change_type === 'emergency') {
            if ($status === null) {
                $emergencyBox = null;
            } elseif ($isStaff) {
                if (in_array($status, [\App\Enums\RfcStatus::CLOSED, \App\Enums\RfcStatus::REJECTED, \App\Enums\RfcStatus::CANCELLED], true)) {
                    $emergencyBox = [
                        'tone' => 'neutral',
                        'title' => 'RFC diajukan dengan kategori Emergency.',
                        'body' => filled($finalClassification)
                            ? 'Klasifikasi akhir: '.$finalClassification.'.'
                            : 'RFC sudah berada pada status akhir.',
                    ];
                } elseif ($rfc->emergency_unconfirmed) {
                    $emergencyBox = [
                        'tone' => 'danger',
                        'title' => 'Emergency — belum dikonfirmasi Change Manager',
                        'body' => 'Analisis risiko dan klasifikasi akhir belum ditetapkan.',
                    ];
                } elseif ($finalClassification === 'Emergency Change') {
                    $emergencyBox = [
                        'tone' => 'danger',
                        'title' => 'Emergency — dikonfirmasi Change Manager',
                        'body' => 'Klasifikasi akhir sudah ditetapkan sebagai Emergency Change.',
                    ];
                } elseif (filled($finalClassification)) {
                    $emergencyBox = [
                        'tone' => 'danger',
                        'title' => 'Diusulkan Emergency, ditetapkan '.$finalClassification,
                        'body' => 'Kategori pengajuan Pemohon berbeda dengan klasifikasi akhir Change Manager.',
                    ];
                }
            } elseif ($rfc->emergency_unconfirmed && ! $isReleaseTeam) {
                $emergencyBox = [
                    'tone' => 'danger',
                    'title' => 'Anda mengajukan kategori Emergency.',
                    'body' => 'Change Manager akan menilai apakah perubahan ini memenuhi syarat darurat.',
                ];
            }
        }
        $visibleDecisions = $isStaff
            ? $rfc->decisions
            : ($isReleaseTeamView
                ? collect()
                : $rfc->decisions->filter(fn ($decision) => in_array($decision->decision_status, [
                    \App\Enums\DecisionStatus::APPROVED->value,
                    \App\Enums\DecisionStatus::REJECTED->value,
                    \App\Enums\DecisionStatus::REVISION_REQUIRED->value,
                ], true)));
        $showRiskAssessment = ($isStaff || $isReleaseTeamView) && $rfc->riskAssessment;
        $decisionList = $isStaff || $isReleaseTeamView ? $rfc->decisions : $visibleDecisions;
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
        $canReject = ! $rfc->trashed()
            && (auth()->user()?->can('reject', $rfc) ?? false);
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

        // Penanda bagian yang tersedia untuk navigasi cepat di halaman detail.
        $hasDispositionSection = $releaseTeamDispositionTask !== null
            || ($isStaff && $rfc->releaseTasks->isNotEmpty());
        $hasReleaseProgressSection = ($isStaff || $isRequesterOwner) && $rfc->releaseTasks->isNotEmpty();
        $hasAttachmentSection = $supportingAttachments->isNotEmpty();
        $hasDecisionSection = $showRiskAssessment || $decisionList->isNotEmpty();
        $hasEvaluationSection = $isStaff
            && ($implementation?->evaluated_at || $rfc->closed_at || filled($rfc->closure_notes));
    @endphp

    {{-- Navigasi kembali --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $returnsToRfcList ? route('change-requests.index') : route('requester.dashboard') }}"
            class="group inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1 pl-1 pr-3 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
            <span
                class="grid h-7 w-7 place-items-center rounded-full bg-slate-100 text-slate-500 transition group-hover:bg-white group-hover:text-blue-700">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
            </span>
            {{ $returnsToRfcList ? 'Kembali ke Daftar RFC' : 'Kembali ke Dashboard' }}
        </a>

        <span class="hidden text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400 sm:inline">
            Permintaan Perubahan Layanan TI
        </span>
    </div>

    {{-- Header utama --}}
    <x-ui.page-header eyebrow="Detail Request for Change" :title="$rfc->displayIdentifier()" :description="$rfc->title">
        <x-slot:icon>
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
        </x-slot:icon>

        <x-slot:meta>
            <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
            <x-category-badge :kategori="$rfc->change_type" :mode="$isStaff ? null : 'diajukan'" />

            @if ($isStaff)
                @if ($rfc->riskAssessment)
                    <x-risk-badge :risiko="$rfc->riskAssessment->risk_level" />
                @endif

                @if (filled($rfc->priority))
                    <x-priority-badge :prioritas="$rfc->priority" />
                @endif

                @if ((string) $rfc->change_type === 'emergency' && $rfc->emergency_unconfirmed)
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-800">
                        <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v3.75m0 3h.008M10.34 3.94 2.7 17.25A2.25 2.25 0 0 0 4.65 20.5h14.7a2.25 2.25 0 0 0 1.95-3.25L13.66 3.94a2.25 2.25 0 0 0-3.32 0Z" />
                        </svg>
                        Emergency belum dikonfirmasi
                    </span>
                @endif
            @endif

            @if ($targetDateIndicator)
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $targetDateIndicator['classes'] }}">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 5.25h13.5A1.5 1.5 0 0 1 20.25 6.75v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" />
                    </svg>
                    Target: {{ $targetDateIndicator['label'] }}
                </span>
            @endif

            @if ($runningDays !== null)
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-600">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {{ $runningDays === 0 ? 'Baru diajukan' : 'Sudah berjalan '.$runningDays.' hari' }}
                </span>
            @endif
        </x-slot:meta>

        @if (! $rfc->trashed())
            <x-slot:actions>
                @if (auth()->user()?->can('assignRelease', $rfc))
                    <x-ui.button :href="route('disposition.assign', $rfc)" navigate>
                        Disposisi
                    </x-ui.button>
                @endif

                @if ($isStaff
                    && in_array($status, [\App\Enums\RfcStatus::APPROVED, \App\Enums\RfcStatus::IN_PROGRESS], true)
                    && ! (auth()->user()?->can('assignRelease', $rfc) ?? false))
                    <x-ui.button href="#disposisi" variant="secondary">
                        Pantau Pelaksanaan
                    </x-ui.button>
                @endif

                @if ($isReleaseTeam && auth()->user()?->can('implement', $rfc))
                    <x-ui.button :href="route('change-requests.implement', ['rfc' => $rfc, 'hasil' => 'success'])" variant="success" navigate>
                        Catat Hasil Implementasi
                    </x-ui.button>
                @endif

                @if ($canEvaluate)
                    <x-ui.button :href="route('change-requests.evaluate', $rfc)" navigate>
                        Catat Evaluasi
                    </x-ui.button>
                @endif

                @if ($canClose)
                    <x-ui.button :href="route('change-requests.close', $rfc)" variant="navy" navigate>
                        Tutup RFC
                    </x-ui.button>
                @endif

                @if ($canReject)
                    <x-ui.button :href="route('change-requests.reject', $rfc)" variant="danger" navigate>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        Tolak RFC
                    </x-ui.button>
                @endif

                @can('exportResult', $rfc)
                    <x-ui.button :href="route('change-requests.result-pdf', $rfc)" variant="secondary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5 7.5 12M12 16.5V3" />
                        </svg>
                        Unduh PDF Hasil RFC
                    </x-ui.button>
                @endcan

                @if ($awaitingEvaluation && $canEvaluate)
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-[11px] font-semibold text-amber-800">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v3.75m0 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        Tutup RFC aktif setelah evaluasi dicatat
                    </span>
                @endif

                @if (auth()->user()?->can('update', $rfc))
                    <x-ui.button :href="route('change-requests.edit', $rfc)" variant="secondary" navigate>
                        {{ $status === \App\Enums\RfcStatus::NEEDS_REVISION ? 'Revisi RFC' : 'Edit RFC' }}
                    </x-ui.button>
                @endif

                <livewire:change-requests.cancel-rfc :rfc="$rfc" />
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    {{-- Posisi RFC saat ini --}}
    @if ($currentPosition)
        <div
            class="flex flex-wrap items-center gap-x-2 gap-y-1.5 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500 shadow-sm">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                </svg>
            </span>
            <span class="font-semibold uppercase tracking-wide text-slate-400">Posisi saat ini:</span>
            @if ($currentPosition['legacy'])
                <span class="font-semibold text-slate-700">Menunggu pemeriksaan</span>
            @elseif ($isStaff)
                <span class="font-semibold text-slate-700">{{ $currentPosition['role'] }}</span>
                @if (filled($currentPosition['stage']))
                    <span class="text-slate-400">&middot;</span>
                    <span>{{ $currentPosition['stage'] }}</span>
                @endif
            @else
                <span class="font-semibold text-slate-700">{{ 'Menunggu: '.($currentPosition['role'] === 'Pemohon' ? 'Anda' : $currentPosition['role']) }}</span>
            @endif
        </div>
    @endif

    {{-- Kotak informasi Emergency --}}
    @if ($emergencyBox)
        <x-ui.alert :tone="$emergencyBox['tone'] === 'neutral' ? 'neutral' : 'danger'" :title="$emergencyBox['title']">
            {{ $emergencyBox['body'] }}
        </x-ui.alert>
    @endif

    {{-- Navigasi cepat antarbagian --}}
    <nav class="flex items-center gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm"
        aria-label="Navigasi bagian detail RFC">
        @php
            $detailNavItems = array_values(array_filter([
                ['href' => '#informasi', 'label' => 'Informasi', 'visible' => true],
                ['href' => '#disposisi', 'label' => 'Disposisi', 'visible' => $hasDispositionSection],
                ['href' => '#progres-tim-rilis', 'label' => 'Progres', 'visible' => $hasReleaseProgressSection],
                ['href' => '#lampiran', 'label' => 'Lampiran', 'visible' => $hasAttachmentSection],
                ['href' => '#implementasi', 'label' => 'Implementasi', 'visible' => $implementation !== null],
                ['href' => '#evaluasi', 'label' => 'Evaluasi', 'visible' => $hasEvaluationSection],
                ['href' => '#keputusan', 'label' => 'Keputusan', 'visible' => $hasDecisionSection],
                ['href' => '#diskusi', 'label' => 'Diskusi', 'visible' => true],
                ['href' => '#riwayat', 'label' => 'Riwayat', 'visible' => true],
            ], fn (array $item): bool => $item['visible']));
        @endphp

        @foreach ($detailNavItems as $detailNavItem)
            <a href="{{ $detailNavItem['href'] }}"
                class="inline-flex shrink-0 items-center rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2">
                {{ $detailNavItem['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-5">
        <!-- Kolom Kiri: Informasi Utama -->
        <div class="space-y-6 lg:col-span-3">
            {{-- Detail Informasi --}}
            <div id="informasi" class="scroll-mt-24">
                <x-dashboard.panel title="Informasi Permintaan Perubahan"
                    description="Identitas permohonan, target pelaksanaan, dan detail teknis perubahan.">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0ZM6.591 18.204a4.5 4.5 0 0 1 6.818-1.987 4.5 4.5 0 0 1 1.591 1.987" />
                        </svg>
                    </x-slot:icon>

                    <dl class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 sm:p-6">
                        <x-ui.fact label="Judul">{{ $rfc->title }}</x-ui.fact>

                        <x-ui.fact label="Sistem / Layanan Target">{{ $rfc->application?->name }}</x-ui.fact>

                        @if (filled($rfc->change_type))
                            <x-ui.fact label="Kategori Perubahan">
                                <x-category-badge :kategori="$rfc->change_type" />
                            </x-ui.fact>
                        @endif

                        @if (! $isStaff && ! $isReleaseTeam && filled($rfc->ticket_number))
                            <x-ui.fact label="Nomor Tiket">{{ $rfc->ticket_number }}</x-ui.fact>
                        @endif

                        @if (filled($rfc->target_implementation_date))
                            <x-ui.fact label="Target Tanggal Pelaksanaan">
                                {{ $rfc->target_implementation_date->copy()->locale('id')->translatedFormat('d M Y') }}
                            </x-ui.fact>
                        @endif

                        <x-ui.fact label="Tanggal Diajukan">
                            {{ $rfc->created_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                            @if ($rfc->created_at)
                                <span class="font-normal text-slate-500">&middot;
                                    {{ $rfc->created_at->copy()->locale('id')->diffForHumans() }}</span>
                            @endif
                        </x-ui.fact>

                        @if ($rfc->is_physical_letter)
                            <x-ui.fact label="Nota Dinas">
                                Ya
                                @if (filled($rfc->physical_letter_number))
                                    <span class="block text-slate-600">{{ $rfc->physical_letter_number }}</span>
                                @endif
                            </x-ui.fact>
                        @endif

                        @if ($isStaff)
                            <x-ui.fact label="Nama Pemohon">{{ $rfc->submittedBy?->name }}</x-ui.fact>

                            @if ($rfc->submittedBy?->opd)
                                <x-ui.fact label="Unit Kerja">{{ $rfc->submittedBy->opd->name }}</x-ui.fact>
                            @endif
                        @endif

                        @if (filled($rfc->description))
                            <x-ui.fact label="Detail Perubahan" class="sm:col-span-2">
                                <p class="whitespace-pre-wrap leading-relaxed text-slate-700">{{ $rfc->description }}</p>
                            </x-ui.fact>
                        @endif

                        @if (filled($rfc->legal_basis))
                            <x-ui.fact label="Dasar Hukum" class="sm:col-span-2">
                                <p class="whitespace-pre-wrap leading-relaxed text-slate-700">{{ $rfc->legal_basis }}</p>
                            </x-ui.fact>
                        @endif

                        @if ($rfc->risk_event instanceof \App\Enums\RiskEvent)
                            <x-ui.fact label="Register Risiko">
                                {{ $rfc->risk_event->optionLabel() }}
                                <button type="button"
                                    data-viewer-url="{{ asset('images/register-risiko-indikatif.png') }}"
                                    data-viewer-title="Lampiran B — Register Risiko Indikatif Tingkat Kota"
                                    data-viewer-type="image"
                                    @click="openAttachmentViewer($el.dataset.viewerUrl, '', $el.dataset.viewerTitle, $el.dataset.viewerType)"
                                    class="mt-1 block text-left text-xs font-semibold text-blue-700 underline-offset-2 hover:underline">
                                    Lihat Lampiran B — Register Risiko Indikatif Tingkat Kota &nearr;
                                </button>
                            </x-ui.fact>
                        @endif

                        <x-ui.fact label="Alasan Perubahan" class="sm:col-span-2">
                            <p class="whitespace-pre-wrap leading-relaxed text-slate-700">{{ $rfc->reason_for_change }}</p>
                        </x-ui.fact>

                        @if (filled($rfc->change_risk))
                            <x-ui.fact label="Risiko Perubahan" class="sm:col-span-2">
                                <p class="whitespace-pre-wrap leading-relaxed text-slate-700">{{ $rfc->change_risk }}</p>
                            </x-ui.fact>
                        @endif

                        @if (filled($rfc->impact_description))
                            <x-ui.fact label="Keterangan" class="sm:col-span-2">
                                <p class="whitespace-pre-wrap leading-relaxed text-slate-700">{{ $rfc->impact_description }}</p>
                            </x-ui.fact>
                        @endif
                    </dl>
                </x-dashboard.panel>
            </div>

            {{-- Surat Persetujuan Bertanda Tangan (arsip data historis) --}}
            @if ($approvalLetters->isNotEmpty())
                <x-dashboard.panel title="Arsip Surat Persetujuan Bertanda Tangan"
                    description="Dokumen persetujuan Pimpinan (Eselon II) yang tersimpan sebagai arsip.">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                d="m9 12.75 1.5 1.5L14.25 10.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </x-slot:icon>

                    <div class="space-y-4 p-5 sm:p-6">
                        @if ($currentApprovalLetter)
                            <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        <span
                                            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-indigo-600 shadow-sm ring-1 ring-indigo-100">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.7"
                                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <p class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                                                <span
                                                    class="min-w-0 truncate">{{ $currentApprovalLetter->original_name }}</span>
                                                <span
                                                    class="shrink-0 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700">
                                                    Arsip
                                                </span>
                                            </p>
                                            <p class="mt-0.5 text-xs text-slate-500">
                                                {{ number_format($currentApprovalLetter->size / 1024, 0, ',', '.') }}
                                                KB
                                                &middot;
                                                {{ $currentApprovalLetter->created_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 text-xs font-semibold">
                                        <button type="button"
                                            data-viewer-url="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $currentApprovalLetter]) }}"
                                            data-viewer-download-url="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $currentApprovalLetter, 'download' => 1]) }}"
                                            data-viewer-title="{{ $currentApprovalLetter->original_name }}"
                                            @click="openAttachmentViewer($el.dataset.viewerUrl, $el.dataset.viewerDownloadUrl, $el.dataset.viewerTitle)"
                                            class="inline-flex items-center rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-indigo-700 transition hover:bg-indigo-100">
                                            Lihat
                                        </button>
                                        <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $currentApprovalLetter, 'download' => 1]) }}"
                                            class="inline-flex items-center rounded-lg border border-transparent bg-indigo-600 px-3 py-1.5 text-white transition hover:bg-indigo-700">
                                            Unduh
                                        </a>
                                    </div>
                                </div>

                                @if ($previousApprovalLetters->isNotEmpty())
                                    <div class="mt-4 border-t border-indigo-100 pt-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                            Riwayat surat persetujuan ({{ $previousApprovalLetters->count() }})
                                        </p>
                                        <ul class="mt-2 divide-y divide-indigo-100">
                                            @foreach ($previousApprovalLetters as $previousApprovalLetter)
                                                <li class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-xs"
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
                                                        <button type="button"
                                                            data-viewer-url="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $previousApprovalLetter]) }}"
                                                            data-viewer-download-url="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $previousApprovalLetter, 'download' => 1]) }}"
                                                            data-viewer-title="{{ $previousApprovalLetter->original_name }}"
                                                            @click="openAttachmentViewer($el.dataset.viewerUrl, $el.dataset.viewerDownloadUrl, $el.dataset.viewerTitle)"
                                                            class="text-indigo-600 hover:text-indigo-700">
                                                            Lihat
                                                        </button>
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
                        @endif
                    </div>
                </x-dashboard.panel>
            @endif

            {{-- Disposisi untuk Tim Rilis --}}
            @if ($releaseTeamDispositionTask)
                <div id="disposisi" class="scroll-mt-24">
                    <x-dashboard.panel title="Disposisi"
                        description="Tugas dan instruksi yang diberikan Change Manager kepada Anda.">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M6 12 3.27 3.125a59.769 59.769 0 0 1 21.485 9.375 59.769 59.769 0 0 1-21.485 9.375L6 12Zm0 0h8.25" />
                            </svg>
                        </x-slot:icon>

                        <div class="p-5 sm:p-6">
                            <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-800">
                                        {{ 'Diberikan oleh '.($releaseTeamDispositionTask->assignedBy?->name ?? 'Change Manager') }}
                                    </p>
                                    <span class="text-[11px] text-slate-500">
                                        {{ $releaseTeamDispositionTask->created_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                    </span>
                                </div>

                                <div class="mt-3">
                                    <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Instruksi</span>
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ trim($releaseTeamDispositionTask->instruction) }}</p>
                                </div>
                            </div>
                        </div>
                    </x-dashboard.panel>
                </div>
            @endif

            @if ($releaseTeamDispositionTask && $isReleaseTeamView)
                <livewire:change-requests.release-task-progress-panel :rfc="$rfc" :key="'release-progress-'.$rfc->id" />
            @endif

            @include('livewire.change-requests.partials.release-task-summary')

            {{-- Disposisi untuk staf --}}
            @if ($isStaff && $rfc->releaseTasks->isNotEmpty())
                <div id="disposisi" class="scroll-mt-24">
                    <x-dashboard.panel title="Disposisi" description="Anggota Tim Rilis yang menerima tugas beserta instruksinya.">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M6 12 3.27 3.125a59.769 59.769 0 0 1 21.485 9.375 59.769 59.769 0 0 1-21.485 9.375L6 12Zm0 0h8.25" />
                            </svg>
                        </x-slot:icon>

                        <div class="space-y-3 p-5 sm:p-6">
                            @foreach ($rfc->releaseTasks as $task)
                                <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4"
                                    wire:key="show-disposition-task-{{ $task->id }}">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-800">
                                                {{ $task->releaseTeamMember?->name ?? 'Anggota Tim Rilis' }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                {{ 'Diberikan oleh '.($task->assignedBy?->name ?? '-') }}
                                            </p>
                                        </div>
                                        <span class="text-[11px] text-slate-500">
                                            {{ $task->created_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                        </span>
                                    </div>
                                    <div class="mt-3">
                                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Instruksi</span>
                                        <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ trim($task->instruction ?? '') }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-dashboard.panel>
                </div>
            @endif

            {{-- Lampiran Pendukung --}}
            @if ($supportingAttachments->isNotEmpty())
                <div id="lampiran" class="scroll-mt-24">
                    <x-dashboard.panel title="Lampiran Pendukung"
                        description="Dokumen pendukung yang diunggah bersama permohonan perubahan.">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                            </svg>
                        </x-slot:icon>

                        <ul class="divide-y divide-slate-100 px-5 sm:px-6">
                            @foreach ($supportingAttachments as $attachment)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3.5 first:pt-4 last:pb-4">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <span
                                            class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.7"
                                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-800">
                                                {{ $attachment->original_name }}</p>
                                            <p class="text-xs text-slate-400">
                                                {{ number_format($attachment->size / 1024, 0, ',', '.') }} KB
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs font-semibold">
                                        <button type="button"
                                            data-viewer-url="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]) }}"
                                            data-viewer-download-url="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment, 'download' => 1]) }}"
                                            data-viewer-title="{{ $attachment->original_name }}"
                                            @click="openAttachmentViewer($el.dataset.viewerUrl, $el.dataset.viewerDownloadUrl, $el.dataset.viewerTitle)"
                                            class="text-blue-700 hover:text-blue-800">Lihat</button>
                                        <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment, 'download' => 1]) }}"
                                            class="text-blue-700 hover:text-blue-800">Unduh</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-dashboard.panel>
                </div>
            @endif

            {{-- Hasil Implementasi --}}
            @if ($implementation)
                <div id="implementasi" class="scroll-mt-24">
                    <x-dashboard.panel title="Hasil Implementasi"
                        description="Catatan pelaksanaan perubahan oleh Tim Rilis.">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                            </svg>
                        </x-slot:icon>

                        <div class="p-5 sm:p-6">
                            @if ($isStaff)
                                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <x-ui.fact label="Hasil">
                                        {{ \App\Enums\ImplementationResult::labelFor($implementation->implementation_result) }}
                                    </x-ui.fact>
                                    <x-ui.fact label="PIC pelaksanaan">{{ $implementation->executor_name }}</x-ui.fact>
                                    <x-ui.fact label="Waktu Mulai">
                                        {{ $implementation->started_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                    </x-ui.fact>
                                    <x-ui.fact label="Waktu Selesai">
                                        {{ $implementation->finished_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                    </x-ui.fact>
                                    <x-ui.fact label="Sumber Laporan" class="sm:col-span-2">
                                        {{ $implementation->report_source ?? '-' }}
                                    </x-ui.fact>
                                    <x-ui.fact label="Ringkasan Hasil" class="sm:col-span-2">
                                        <p class="whitespace-pre-wrap leading-relaxed text-slate-700">{{ $implementation->summary }}</p>
                                    </x-ui.fact>
                                    <x-ui.fact label="Hasil Pengujian" class="sm:col-span-2">
                                        <p class="whitespace-pre-wrap leading-relaxed text-slate-700">{{ $implementation->test_results }}</p>
                                    </x-ui.fact>
                                    @if (filled($implementation->failure_notes))
                                        <x-ui.fact label="Catatan Belum Berhasil" class="sm:col-span-2">
                                            <p class="whitespace-pre-wrap leading-relaxed text-slate-700">
                                                {{ $implementation->failure_notes }}</p>
                                        </x-ui.fact>
                                    @endif
                                    <x-ui.fact label="Tindak Lanjut">
                                        {{ filled($implementation->follow_up_action) ? \App\Enums\FollowUpAction::labelFor($implementation->follow_up_action) : '-' }}
                                    </x-ui.fact>
                                    @if (filled($implementation->follow_up_notes))
                                        <x-ui.fact label="Keterangan Tindak Lanjut">
                                            <p class="whitespace-pre-wrap leading-relaxed text-slate-700">
                                                {{ $implementation->follow_up_notes }}</p>
                                        </x-ui.fact>
                                    @endif
                                </dl>

                                <div class="mt-6 border-t border-slate-100 pt-4">
                                    <h4 class="mb-3 text-sm font-bold text-slate-700">
                                        Dokumen perubahan dan bukti dukung ({{ $implementationEvidences->count() }} file)
                                    </h4>

                                    @if ($implementationEvidences->isEmpty())
                                        <p class="text-sm text-slate-500">Tidak ada berkas bukti.</p>
                                    @else
                                        <ul class="divide-y divide-slate-100">
                                            @foreach ($implementationEvidences as $evidence)
                                                <li
                                                    class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-medium text-slate-800">
                                                            {{ $evidence->original_filename }}</p>
                                                        <p class="text-xs text-slate-400">
                                                            {{ number_format($evidence->file_size / 1024, 0, ',', '.') }}
                                                            KB
                                                            &middot; {{ $evidence->mime_type }}
                                                        </p>
                                                    </div>
                                                    <div class="flex items-center gap-3 text-xs font-semibold">
                                                        <button type="button"
                                                            data-viewer-url="{{ route('change-requests.evidences.show', ['rfc' => $rfc, 'evidence' => $evidence]) }}"
                                                            data-viewer-download-url="{{ route('change-requests.evidences.show', ['rfc' => $rfc, 'evidence' => $evidence, 'download' => 1]) }}"
                                                            data-viewer-title="{{ $evidence->original_filename }}"
                                                            @click="openAttachmentViewer($el.dataset.viewerUrl, $el.dataset.viewerDownloadUrl, $el.dataset.viewerTitle)"
                                                            class="text-blue-700 hover:text-blue-800">Lihat</button>
                                                        <a href="{{ route('change-requests.evidences.show', ['rfc' => $rfc, 'evidence' => $evidence, 'download' => 1]) }}"
                                                            class="text-blue-700 hover:text-blue-800">Unduh</a>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @else
                                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <x-ui.fact label="Hasil">
                                        {{ \App\Enums\ImplementationResult::labelFor($implementation->implementation_result) }}
                                    </x-ui.fact>
                                    <x-ui.fact label="Waktu Pelaksanaan">
                                        {{ $implementation->started_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                        s.d.
                                        {{ $implementation->finished_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                    </x-ui.fact>
                                    <x-ui.fact label="Ringkasan Hasil" class="sm:col-span-2">
                                        <p class="whitespace-pre-wrap leading-relaxed text-slate-700">{{ $implementation->summary }}</p>
                                    </x-ui.fact>
                                    @if (filled($implementation->failure_notes))
                                        <x-ui.fact label="Catatan Belum Berhasil" class="sm:col-span-2">
                                            <p class="whitespace-pre-wrap leading-relaxed text-slate-700">
                                                {{ $implementation->failure_notes }}</p>
                                        </x-ui.fact>
                                    @endif
                                    <x-ui.fact label="Tindak Lanjut">
                                        {{ filled($implementation->follow_up_action) ? \App\Enums\FollowUpAction::labelFor($implementation->follow_up_action) : '-' }}
                                    </x-ui.fact>
                                    @if (filled($implementation->follow_up_notes))
                                        <x-ui.fact label="Keterangan Tindak Lanjut">
                                            <p class="whitespace-pre-wrap leading-relaxed text-slate-700">
                                                {{ $implementation->follow_up_notes }}</p>
                                        </x-ui.fact>
                                    @endif
                                    <x-ui.fact label="Dokumen perubahan dan bukti dukung" class="sm:col-span-2">
                                        Bukti pendukung: {{ $implementationEvidences->count() }} file
                                    </x-ui.fact>
                                </dl>
                            @endif
                        </div>
                    </x-dashboard.panel>
                </div>
            @endif

            {{-- Evaluasi dan Penutupan --}}
            @if ($hasEvaluationSection)
                <div id="evaluasi" class="scroll-mt-24">
                    <x-dashboard.panel title="Evaluasi dan Penutupan"
                        description="Hasil evaluasi Change Manager dan catatan penutupan RFC.">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.745 3.745 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                            </svg>
                        </x-slot:icon>

                        <div class="space-y-4 p-5 sm:p-6">
                            @if ($implementation?->evaluated_at)
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <h4 class="text-sm font-bold text-emerald-900">Catatan Evaluasi</h4>
                                        <span class="text-[11px] text-emerald-800">
                                            {{ $implementation->evaluated_at->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                        </span>
                                    </div>
                                    <p class="mt-2 whitespace-pre-wrap text-sm text-emerald-950">{{ trim($implementation->evaluation_notes ?? '') }}</p>
                                    <p class="mt-3 text-xs text-emerald-800">
                                        Dievaluasi oleh {{ $implementation->evaluatedBy?->name ?? 'Change Manager' }}
                                    </p>
                                </div>
                            @endif

                            @if ($rfc->closed_at || filled($rfc->closure_notes))
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <h4 class="text-sm font-bold text-slate-800">Catatan Penutupan</h4>
                                        @if ($rfc->closed_at)
                                            <span class="text-[11px] text-slate-500">
                                                {{ $rfc->closed_at->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-2 whitespace-pre-wrap text-sm text-slate-700">{{ trim($rfc->closure_notes ?: '-') }}</p>
                                    @if ($rfc->closedBy || $rfc->closed_at)
                                        <p class="mt-3 text-xs text-slate-500">
                                            Ditutup oleh {{ $rfc->closedBy?->name ?? '-' }}
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </x-dashboard.panel>
                </div>
            @endif

            {{-- Penilaian Layanan oleh Pemohon --}}
            @can('rate', $rfc)
                <x-dashboard.panel title="Beri Penilaian Layanan"
                    description="Penilaian hanya dapat dikirim satu kali dan tidak dapat diubah setelah terkirim.">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                        </svg>
                    </x-slot:icon>

                    <div class="p-5 sm:p-6">
                        @if (filled($satisfactionMessage))
                            <x-ui.alert tone="success" class="mb-4">{{ $satisfactionMessage }}</x-ui.alert>
                        @endif

                        @error('satisfactionRating')
                            <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
                        @enderror

                        <p class="mb-4 text-sm text-slate-600">
                            Penilaian hanya dapat dikirim satu kali dan tidak dapat diubah setelah terkirim.
                        </p>

                        <form wire:submit="submitSatisfaction" class="space-y-5">
                            <fieldset x-data="{ hover: 0, selected: @js($satisfactionRating) }"
                                x-on:mouseleave="hover = 0">
                                <legend class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Kepuasan terhadap layanan (1 = sangat tidak puas, 5 = sangat puas)
                                </legend>

                                <div class="mt-2 flex items-center gap-1">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <label
                                            class="group cursor-pointer rounded-lg p-1 transition focus-within:ring-2 focus-within:ring-amber-500 focus-within:ring-offset-2"
                                            x-on:mouseenter="hover = {{ $i }}">
                                            <input type="radio" name="satisfactionRating" value="{{ $i }}"
                                                wire:model.live="satisfactionRating"
                                                x-on:change="selected = {{ $i }}" class="peer sr-only">
                                            <span class="sr-only">{{ $i }} dari 5 bintang</span>
                                            <svg class="h-9 w-9 transition-colors duration-150 {{ $satisfactionRating >= $i ? 'text-amber-400' : 'text-slate-300' }}"
                                                x-bind:class="{
                                                    'text-amber-400': (hover || selected) >= {{ $i }},
                                                    'text-slate-300': (hover || selected) < {{ $i }},
                                                }"
                                                fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path
                                                    d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.77l-5.2 2.74.99-5.79-4.21-4.1 5.82-.85L10 1.5z" />
                                            </svg>
                                        </label>
                                    @endfor
                                </div>

                                @if ($satisfactionRating > 0)
                                    <p class="mt-1 text-xs font-semibold text-amber-700">
                                        {{ $satisfactionRating }} dari 5 bintang dipilih
                                    </p>
                                @endif
                            </fieldset>

                            <div>
                                <label for="satisfaction-comment" class="block text-xs font-semibold text-slate-600">
                                    Komentar
                                    ({{ $satisfactionRating >= 1 && $satisfactionRating <= 4 ? 'wajib' : 'opsional' }})
                                    @if ($satisfactionRating >= 1 && $satisfactionRating <= 4)
                                        <span class="text-rose-600" aria-hidden="true">*</span>
                                    @endif
                                </label>
                                <textarea id="satisfaction-comment" rows="3" maxlength="1000"
                                    wire:model.live.debounce.500ms="satisfactionComment"
                                    @if ($satisfactionRating >= 1 && $satisfactionRating <= 4) aria-required="true" @endif
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100"
                                    placeholder="Sampaikan masukan Anda tentang layanan ini."></textarea>
                                @if ($satisfactionRating >= 1 && $satisfactionRating <= 4)
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        Ceritakan kendala atau saran Anda, minimal 5 karakter.
                                    </p>
                                @endif
                                <p class="mt-1 text-right text-[11px] text-slate-400">
                                    {{ mb_strlen($satisfactionComment) }}/1000 karakter
                                </p>
                                @error('satisfactionComment')
                                    <p class="mt-1 text-xs font-medium text-rose-700" role="alert">{{ $message }}</p>
                                @enderror
                            </div>

                            <x-ui.button type="submit" variant="warning" wire:loading.attr="disabled"
                                wire:target="submitSatisfaction">
                                <span wire:loading.remove wire:target="submitSatisfaction">Kirim Penilaian</span>
                                <span wire:loading wire:target="submitSatisfaction">Mengirim...</span>
                            </x-ui.button>
                        </form>
                    </div>
                </x-dashboard.panel>
            @endcan

            @can('viewSatisfaction', $rfc)
                @if ($rfc->satisfaction)
                    <x-dashboard.panel title="Penilaian Pemohon"
                        description="Umpan balik yang diberikan Pemohon setelah layanan selesai.">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                            </svg>
                        </x-slot:icon>

                        <div class="p-5 sm:p-6">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex items-center gap-1">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <svg class="h-7 w-7 {{ $i <= $rfc->satisfaction->rating ? 'text-amber-400' : 'text-slate-200' }}"
                                            fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path
                                                d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.77l-5.2 2.74.99-5.79-4.21-4.1 5.82-.85L10 1.5z" />
                                        </svg>
                                    @endfor
                                </div>
                                <p class="text-sm font-bold text-slate-800">{{ $rfc->satisfaction->rating }}/5</p>
                                <p class="text-xs text-slate-500">
                                    {{ $rfc->satisfaction->created_at?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') }}
                                    WIB
                                </p>
                            </div>

                            @if (filled($rfc->satisfaction->comment))
                                <p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">
                                    {{ $rfc->satisfaction->comment }}</p>
                            @else
                                <p class="mt-3 text-sm italic text-slate-400">Tanpa komentar.</p>
                            @endif
                        </div>
                    </x-dashboard.panel>
                @endif
            @endcan

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
                <x-dashboard.panel title="Ditetapkan Petugas"
                    description="Klasifikasi dan penetapan Change Manager.">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </x-slot:icon>

                    <dl class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 sm:p-6">
                        @if (filled($officerTicketNumber))
                            <x-ui.fact label="Nomor Tiket">{{ $officerTicketNumber }}</x-ui.fact>
                        @endif
                        @if (filled($officerCategory))
                            <x-ui.fact label="Kategori Layanan">{{ $officerCategory }}</x-ui.fact>
                        @endif
                        @if (filled($officerPriority))
                            <x-ui.fact label="Prioritas">
                                <x-priority-badge :prioritas="$rfc->priority" />
                            </x-ui.fact>
                        @endif
                        @if ($showSubmittedChangeType)
                            <x-ui.fact label="Kategori diusulkan Pemohon">
                                <x-category-badge :kategori="$rfc->change_type" />
                            </x-ui.fact>
                        @endif
                        @if (filled($officerFinalClassification))
                            <x-ui.fact label="Klasifikasi ditetapkan Change Manager">
                                {{ $officerFinalClassification }}
                            </x-ui.fact>
                        @endif
                        @if (filled($officerAdminNote))
                            <x-ui.fact label="Catatan Pemeriksaan Awal" class="sm:col-span-2">
                                <p class="whitespace-pre-wrap leading-relaxed text-slate-700">
                                    {{ $officerAdminNote }}</p>
                            </x-ui.fact>
                        @endif
                    </dl>
                </x-dashboard.panel>
            @endif

            {{-- Alasan Penolakan / Permintaan Revisi --}}
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
                <x-ui.alert tone="danger" title="Alasan Penolakan">
                    <p class="whitespace-pre-wrap">{{ $rejectionReason }}</p>
                </x-ui.alert>
            @endif
            @if (filled($revisionReason))
                <x-ui.alert tone="warning" title="Alasan Permintaan Revisi">
                    <p class="whitespace-pre-wrap">{{ $revisionReason }}</p>
                </x-ui.alert>
            @endif

            @php
                $cancellationReason = $status === \App\Enums\RfcStatus::CANCELLED
                    ? app(\App\Services\RfcHistoryService::class)->cancellationReason($rfc)
                    : null;
            @endphp
            @if (filled($cancellationReason))
                <x-ui.alert tone="neutral" title="Alasan Pembatalan">
                    <p class="whitespace-pre-wrap">{{ $cancellationReason }}</p>
                </x-ui.alert>
            @endif

            {{-- Panel Keputusan --}}
            @if ($hasDecisionSection)
                <div id="keputusan" class="scroll-mt-24">
                    <x-dashboard.panel title="Hasil Evaluasi & Keputusan"
                        description="Penilaian risiko dan keputusan Change Manager atas permohonan ini.">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
                            </svg>
                        </x-slot:icon>

                        <div class="space-y-6 p-5 sm:p-6">
                            @if ($showRiskAssessment)
                                <div>
                                    <h4 class="mb-3 text-sm font-bold text-slate-700">Penilaian Risiko (Change Manager)</h4>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                                            <span class="block text-xs text-slate-500">Tingkat Risiko</span>
                                            <div class="mt-1">
                                                <x-risk-badge :risiko="$rfc->riskAssessment->risk_level" />
                                            </div>
                                        </div>
                                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                                            <span class="block text-xs text-slate-500">Dampak</span>
                                            <span
                                                class="mt-1 block font-bold text-slate-800">{{ $rfc->riskAssessment->impact_level }}</span>
                                        </div>
                                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                                            <span class="block text-xs text-slate-500">Klasifikasi</span>
                                            <span
                                                class="mt-1 block font-bold text-blue-700">{{ $rfc->riskAssessment->classification }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if ($decisionList->isNotEmpty())
                                <div>
                                    <h4 class="mb-3 text-sm font-bold text-slate-700">Keputusan Change Manager</h4>
                                    <div class="space-y-3">
                                        @foreach ($decisionList as $decision)
                                            @php
                                                $decisionCard = match ($decision->decision_status) {
                                                    \App\Enums\DecisionStatus::APPROVED->value => 'bg-emerald-50 border-emerald-100',
                                                    \App\Enums\DecisionStatus::REJECTED->value => 'bg-rose-50 border-rose-100',
                                                    \App\Enums\DecisionStatus::REVISION_REQUIRED->value,
                                                    \App\Enums\DecisionStatus::RETURNED_TO_ADMIN->value => 'bg-amber-50 border-amber-100',
                                                    default => 'bg-slate-50 border-slate-200',
                                                };
                                                $decisionBadge = match ($decision->decision_status) {
                                                    \App\Enums\DecisionStatus::APPROVED->value => 'bg-emerald-200 text-emerald-800',
                                                    \App\Enums\DecisionStatus::REJECTED->value => 'bg-rose-200 text-rose-800',
                                                    \App\Enums\DecisionStatus::REVISION_REQUIRED->value,
                                                    \App\Enums\DecisionStatus::RETURNED_TO_ADMIN->value => 'bg-amber-200 text-amber-800',
                                                    default => 'bg-slate-200 text-slate-700',
                                                };
                                                // Status lama "returned_to_admin" hanya ada pada data
                                                // historis; labelnya dibuat netral di halaman detail.
                                                $decisionLabel = $decision->decision_status === \App\Enums\DecisionStatus::RETURNED_TO_ADMIN->value
                                                    ? 'Dikembalikan untuk pemeriksaan'
                                                    : \App\Enums\DecisionStatus::labelFor($decision->decision_status);
                                            @endphp
                                            <div class="rounded-xl border p-4 {{ $decisionCard }}">
                                                <div class="mb-2 flex items-start justify-between gap-3">
                                                    @if ($isStaff || $isReleaseTeamView)
                                                        <div>
                                                            <span
                                                                class="font-bold text-slate-800">{{ $decision->snapshot_authority_name ?? 'Change Manager' }}</span>
                                                            @if (filled($decision->snapshot_authority_position))
                                                                <span
                                                                    class="block text-xs text-slate-500">{{ $decision->snapshot_authority_position }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    <span
                                                        class="ml-auto rounded-full px-2.5 py-1 text-xs font-bold {{ $decisionBadge }}">
                                                        {{ $decisionLabel }}
                                                    </span>
                                                </div>
                                                <p class="mt-2 text-sm text-slate-700"><span
                                                        class="font-semibold">Catatan/Alasan:</span>
                                                    {{ $decision->reason ?? '-' }}
                                                </p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </x-dashboard.panel>
                </div>
            @endif

            {{-- Diskusi --}}
            <div id="diskusi" class="scroll-mt-24">
                <x-dashboard.panel title="Diskusi"
                    description="Tanyakan detail, minta klarifikasi, atau catat tindak lanjut.">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                        </svg>
                    </x-slot:icon>

                    <div class="p-5 sm:p-6">
                        <livewire:change-requests.discussion :rfc="$rfc" :key="'discussion-'.$rfc->id" />
                    </div>
                </x-dashboard.panel>
            </div>
        </div>

        <!-- Kolom Kanan: Riwayat Proses -->
        <aside class="lg:col-span-2 lg:self-stretch">
            <div id="riwayat"
                class="scroll-mt-24 lg:sticky lg:top-24 lg:max-h-[calc(100dvh-7rem)] lg:overflow-y-auto lg:overscroll-contain lg:pr-1">
                <livewire:change-requests.process-history-timeline :rfc="$rfc" :key="'process-history-'.$rfc->id" />
            </div>
        </aside>
    </div>

    {{-- Penampil lampiran (modal) --}}
    <div x-show="attachmentViewerOpen" x-cloak x-transition.opacity.duration.150ms
        class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 backdrop-blur-sm sm:p-4"
        role="dialog" aria-modal="true" aria-labelledby="attachment-viewer-title"
        @click.self="closeAttachmentViewer()">
        <div
            class="flex h-full w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[90vh] sm:max-w-5xl sm:rounded-2xl">
            <div class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
                <span
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </span>

                <div class="min-w-0 flex-1">
                    <p id="attachment-viewer-title" class="truncate text-sm font-semibold text-slate-800"
                        x-text="attachmentViewerTitle"></p>
                    <p class="text-[11px] text-slate-400"
                        x-text="attachmentViewerType === 'image' ? 'Pratinjau gambar' : 'Pratinjau lampiran PDF'"></p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <a :href="attachmentViewerUrl" target="_blank" rel="noopener"
                        class="hidden rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 sm:inline-flex">
                        Buka di tab baru
                    </a>
                    <a :href="attachmentViewerDownloadUrl"
                        x-bind:download="attachmentViewerType === 'image' ? attachmentViewerTitle : null"
                        class="inline-flex rounded-lg border border-transparent bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700">
                        Unduh
                    </a>
                    <button type="button" @click="closeAttachmentViewer()" aria-label="Tutup pratinjau lampiran"
                        class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 bg-slate-100 sm:flex-none">
                <template x-if="attachmentViewerUrl && attachmentViewerType === 'image'">
                    <div class="h-[calc(100dvh-4.25rem)] w-full overflow-auto p-3 sm:h-[75vh] sm:p-4">
                        <img :src="attachmentViewerUrl" :alt="attachmentViewerTitle"
                            class="mx-auto h-auto w-auto max-w-full rounded-lg bg-white shadow-sm">
                    </div>
                </template>
                <template x-if="attachmentViewerUrl && attachmentViewerType !== 'image'">
                    <iframe :src="attachmentViewerUrl" :title="attachmentViewerTitle"
                        class="h-[calc(100dvh-4.25rem)] w-full sm:h-[75vh]"></iframe>
                </template>
            </div>
        </div>
    </div>
</div>
