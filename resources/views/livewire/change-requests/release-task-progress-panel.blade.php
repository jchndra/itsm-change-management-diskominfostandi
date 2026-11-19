@php
    $progressTask = $progressTask ?? null;
@endphp

<div id="progres-pengerjaan" wire:key="release-progress-panel-{{ $progressTask?->id ?? 'none' }}"
    x-data
    x-on:release-progress-updated.window="$nextTick(() => $el.scrollIntoView({ block: 'nearest' }))"
    class="rounded-xl border border-emerald-100 bg-white p-4 shadow-sm sm:p-6">
@if ($progressTask === null)
    <p class="text-sm text-slate-500">Tugas Tim Rilis tidak ditemukan.</p>
@else
@php
    $progressStatusBadge = match ($progressTask->execution_status) {
        'pending' => ['label' => 'Menunggu dimulai', 'classes' => 'bg-slate-100 text-slate-700 ring-slate-200'],
        'in_progress' => ['label' => 'Sedang dikerjakan', 'classes' => 'bg-blue-100 text-blue-700 ring-blue-200'],
        'successful' => ['label' => 'Selesai', 'classes' => 'bg-emerald-100 text-emerald-700 ring-emerald-200'],
        'rolled_back' => ['label' => 'Reject', 'classes' => 'bg-rose-100 text-rose-700 ring-rose-200'],
        default => ['label' => $progressTask->execution_status, 'classes' => 'bg-slate-100 text-slate-700 ring-slate-200'],
    };
    $progressStatusLabel = $progressStatusBadge['label'];
    $progressEditable = in_array($progressTask->execution_status, ['pending', 'in_progress'], true)
        && ! $rfc->trashed();
    $hasRiskAnalysis = $progressTask->riskAnalysis !== null;
    $hasEstimate = $progressTask->estimated_days !== null;
    $canStart = $hasEstimate && $hasRiskAnalysis;

    $progressCountdown = null;

    if ($progressTask->execution_status === 'in_progress' && $progressTask->scheduled_end !== null) {
        $secondsLeft = $progressTask->scheduled_end->getTimestamp() - now()->getTimestamp();

        if ($secondsLeft < 0) {
            $progressCountdown = [
                'label' => 'Terlambat '.(int) ceil(abs($secondsLeft) / 86400).' hari',
                'classes' => 'bg-rose-100 text-rose-700 ring-rose-200',
            ];
        } else {
            $remainingDays = (int) ceil($secondsLeft / 86400);
            $progressCountdown = [
                'label' => 'Sisa '.$remainingDays.' hari',
                'classes' => $remainingDays <= 1
                    ? 'bg-amber-100 text-amber-800 ring-amber-200'
                    : 'bg-emerald-100 text-emerald-700 ring-emerald-200',
            ];
        }
    }

    $progressSteps = [
        [
            'label' => 'Analisis risiko',
            'state' => $hasRiskAnalysis ? 'done' : 'active',
        ],
        [
            'label' => 'Dikerjakan',
            'state' => match (true) {
                in_array($progressTask->execution_status, ['successful', 'rolled_back'], true) => 'done',
                $progressTask->execution_status === 'in_progress' => 'active',
                $canStart => 'active',
                default => 'upcoming',
            },
        ],
        [
            'label' => 'Selesai / Reject',
            'state' => in_array($progressTask->execution_status, ['successful', 'rolled_back'], true)
                ? 'done'
                : 'upcoming',
        ],
    ];

    $planFieldClasses = 'mt-2 block w-full resize-y rounded-lg border bg-white px-3.5 py-3 text-sm leading-relaxed text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2';

    $planFieldStateClasses = fn (string $field): string => $errors->has($field)
        ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500/30'
        : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-500/30';

    $estimateFieldClasses = 'mt-1 block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500';

    $riskAnalysis = $progressTask->riskAnalysis;
@endphp

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </span>
            <div>
                <h3 class="text-lg font-bold text-slate-800">Progres Pengerjaan</h3>
                <p class="text-xs text-slate-500">Analisis risiko, pelaksanaan, dan riwayat tugas Anda.</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $progressStatusBadge['classes'] }}">
            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
            {{ $progressStatusLabel }}
        </span>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Status</p>
            <p class="mt-1 font-semibold text-slate-800">{{ $progressStatusLabel }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Estimasi</p>
            <p class="mt-1 font-semibold text-slate-800">
                {{ $hasEstimate ? $progressTask->estimated_days.' hari' : 'Belum ditetapkan' }}
            </p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Mulai (WIB)</p>
            <p class="mt-1 font-semibold text-slate-800">
                {{ $progressTask->scheduled_start
                    ? $progressTask->scheduled_start->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i')
                    : '—' }}
            </p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Target selesai (WIB)</p>
            <p class="mt-1 font-semibold text-slate-800">
                {{ $progressTask->scheduled_end
                    ? $progressTask->scheduled_end->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i')
                    : '—' }}
            </p>
            @if ($progressCountdown !== null)
                <span class="mt-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $progressCountdown['classes'] }}">
                    {{ $progressCountdown['label'] }}
                </span>
            @endif
        </div>
    </div>

    <ol class="mt-5 grid grid-cols-3 gap-1" aria-label="Tahapan pengerjaan">
        @foreach ($progressSteps as $step)
            @php
                $stepStateClasses = match ($step['state']) {
                    'done' => 'bg-emerald-600 text-white',
                    'active' => 'bg-blue-600 text-white',
                    default => 'bg-slate-200 text-slate-500',
                };
                $stepLabelClasses = match ($step['state']) {
                    'done' => 'text-emerald-700',
                    'active' => 'text-blue-700',
                    default => 'text-slate-500',
                };
            @endphp
            <li class="relative flex flex-col items-center text-center">
                @unless ($loop->last)
                    <span class="absolute left-1/2 top-4 h-0.5 w-full {{ $step['state'] === 'done' ? 'bg-emerald-300' : 'bg-slate-200' }}" aria-hidden="true"></span>
                @endunless
                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full {{ $stepStateClasses }}">
                    @if ($step['state'] === 'done')
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    @else
                        <span class="text-xs font-bold">{{ $loop->iteration }}</span>
                    @endif
                </span>
                <span class="relative z-10 mt-2 bg-white px-1 text-xs font-semibold leading-tight {{ $stepLabelClasses }}">
                    {{ $step['label'] }}
                </span>
            </li>
        @endforeach
    </ol>

    @if (filled($progressMessage))
        <p class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800" role="status" aria-live="polite">
            {{ $progressMessage }}
        </p>
    @endif

    @if (! $showRiskForm)
        @error('progress')
            <p class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-800" role="alert">
                {{ $message }}
            </p>
        @enderror
    @endif

    @if ($progressTask->execution_status === 'pending')
        <p class="mt-4 text-xs text-slate-500">
            Lengkapi analisis risiko, lalu klik Mulai Pengerjaan untuk dapat mencatat hasil implementasi.
        </p>
    @endif

    @if ($progressEditable)
        <div class="mt-5 space-y-4 border-t border-slate-100 pt-5">
            @if ($progressTask->execution_status === 'in_progress')
                @can('implement', $rfc)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-700 text-white">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <h4 class="text-base font-bold text-slate-900">Selesaikan pekerjaan</h4>
                                <p class="mt-1 text-sm text-slate-600">
                                    Pilih Setuju bila pekerjaan tuntas, atau Reject bila pelaksanaan dihentikan. Setelah laporan disimpan, RFC masuk review Change Manager.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="flex flex-col gap-1.5">
                                <a href="{{ route('change-requests.implement', ['rfc' => $rfc, 'hasil' => 'success']) }}" wire:navigate
                                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">
                                    Setuju
                                </a>
                                <p class="text-xs text-slate-600">Isi laporan hasil pengerjaan yang berhasil.</p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <a href="{{ route('change-requests.implement', ['rfc' => $rfc, 'hasil' => 'failed']) }}" wire:navigate
                                    class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-700 focus-visible:ring-offset-2">
                                    Reject
                                </a>
                                <p class="text-xs font-medium text-rose-700">Wajib menuliskan alasan reject.</p>
                            </div>
                        </div>
                    </div>
                @endcan
            @endif

            @if ($hasRiskAnalysis && ! $showRiskForm)
                <div wire:key="risk-summary-{{ $progressTask->id }}"
                    class="rounded-2xl border border-emerald-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-emerald-100 bg-emerald-50/60 px-4 py-4 sm:px-5">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/20">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v5.25c0 4.5 3.15 8.4 7.5 9.75 4.35-1.35 7.5-5.25 7.5-9.75V6L12 3Z" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-bold text-slate-900">Analisis risiko</h4>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        Tersimpan
                                    </span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
                                        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        {{ $hasEstimate ? $progressTask->estimated_days.' hari' : 'Estimasi belum ditetapkan' }}
                                    </span>
                                    @if ($progressTask->planned_start_on !== null)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
                                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                            </svg>
                                            rencana mulai {{ $progressTask->planned_start_on->copy()->locale('id')->translatedFormat('d M Y') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($progressTask->execution_status === 'pending')
                            <button type="button" wire:click="toggleRiskForm"
                                class="inline-flex min-h-10 w-full items-center justify-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 sm:w-auto">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                </svg>
                                Ubah analisis risiko
                            </button>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3 sm:p-5">
                        @foreach ([
                            [
                                'label' => 'Rencana kegiatan',
                                'value' => $riskAnalysis->activity_plan,
                                'icon' => 'list',
                            ],
                            [
                                'label' => 'Rencana perubahan',
                                'value' => $riskAnalysis->change_plan,
                                'icon' => 'refresh',
                            ],
                            [
                                'label' => 'Mekanisme rollback',
                                'value' => $riskAnalysis->rollback_mechanism,
                                'icon' => 'shield',
                            ],
                        ] as $riskItem)
                            <div class="min-w-0 rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white text-emerald-700 ring-1 ring-inset ring-emerald-100">
                                        @switch($riskItem['icon'])
                                            @case('list')
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                                </svg>
                                                @break
                                            @case('refresh')
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356m-4.992 4.992 3.181-3.183a8.25 8.25 0 0 0-13.803 3.3M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.3" />
                                                </svg>
                                                @break
                                            @default
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v5.25c0 4.5 3.15 8.4 7.5 9.75 4.35-1.35 7.5-5.25 7.5-9.75V6L12 3Z" />
                                                </svg>
                                        @endswitch
                                    </span>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $riskItem['label'] }}</p>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-800">{{ $riskItem['value'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($progressTask->execution_status === 'pending' && $showRiskForm)
                <form wire:submit="saveRiskAnalysis" wire:key="risk-form-{{ $progressTask->id }}" lang="id"
                    x-data="{
                        estimate: {{ $hasEstimate || filled($estimatedDays) ? 'true' : 'false' }},
                        activity: {{ mb_strlen((string) $activityPlan) }},
                        change: {{ mb_strlen((string) $changePlan) }},
                        rollback: {{ mb_strlen((string) $rollbackMechanism) }}
                    }"
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {{-- Header form analisis risiko --}}
                    <div class="rounded-t-2xl border-b border-slate-100 bg-gradient-to-br from-emerald-50 via-white to-white px-4 py-4 sm:px-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/20">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v5.25c0 4.5 3.15 8.4 7.5 9.75 4.35-1.35 7.5-5.25 7.5-9.75V6L12 3Z" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-base font-bold text-slate-900">Analisis risiko</h4>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            Wajib sebelum mulai
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-600">
                                        Lengkapi 4 langkah berikut sebelum pengerjaan dimulai. Data ini menjadi acuan Change Manager dan Pemohon.
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex shrink-0 items-center rounded-md bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-200">
                                4 langkah
                            </span>
                        </div>

                        <ol class="mt-4 grid grid-cols-4 gap-1.5" aria-label="Langkah analisis risiko">
                            <li>
                                <span class="relative block h-1.5 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                                    <span class="absolute inset-0 rounded-full bg-emerald-500 transition-opacity" :class="estimate ? 'opacity-100' : 'opacity-0'"></span>
                                </span>
                                <span class="mt-1.5 hidden truncate text-[10px] font-semibold transition-colors sm:block" :class="estimate ? 'text-emerald-700' : 'text-slate-500'">Estimasi</span>
                            </li>
                            <li>
                                <span class="relative block h-1.5 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                                    <span class="absolute inset-0 rounded-full bg-emerald-500 transition-opacity" :class="activity >= 10 ? 'opacity-100' : 'opacity-0'"></span>
                                </span>
                                <span class="mt-1.5 hidden truncate text-[10px] font-semibold transition-colors sm:block" :class="activity >= 10 ? 'text-emerald-700' : 'text-slate-500'">Kegiatan</span>
                            </li>
                            <li>
                                <span class="relative block h-1.5 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                                    <span class="absolute inset-0 rounded-full bg-emerald-500 transition-opacity" :class="change >= 10 ? 'opacity-100' : 'opacity-0'"></span>
                                </span>
                                <span class="mt-1.5 hidden truncate text-[10px] font-semibold transition-colors sm:block" :class="change >= 10 ? 'text-emerald-700' : 'text-slate-500'">Perubahan</span>
                            </li>
                            <li>
                                <span class="relative block h-1.5 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                                    <span class="absolute inset-0 rounded-full bg-emerald-500 transition-opacity" :class="rollback >= 10 ? 'opacity-100' : 'opacity-0'"></span>
                                </span>
                                <span class="mt-1.5 hidden truncate text-[10px] font-semibold transition-colors sm:block" :class="rollback >= 10 ? 'text-emerald-700' : 'text-slate-500'">Rollback</span>
                            </li>
                        </ol>
                    </div>

                    <div class="space-y-3 p-4 sm:p-5">
                        {{-- Langkah 1: estimasi kegiatan --}}
                        <section class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4" aria-labelledby="risk-step-estimate-{{ $progressTask->id }}">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold transition-colors"
                                    :class="estimate ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/20' : 'bg-white text-emerald-700 ring-1 ring-inset ring-emerald-200'">1</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h5 id="risk-step-estimate-{{ $progressTask->id }}" class="text-sm font-bold text-slate-900">Estimasi kegiatan</h5>
                                        @if ($hasEstimate)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                </svg>
                                                Terkunci
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-500 ring-1 ring-inset ring-slate-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                                Perlu diisi
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 text-xs leading-relaxed text-slate-600">Perkiraan lama pengerjaan dan tanggal rencana mulai.</p>

                                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <label for="estimated-days-{{ $progressTask->id }}" class="block text-sm font-semibold text-slate-700">
                                                Estimasi pengerjaan (hari)
                                            </label>
                                            <div class="relative">
                                                <input id="estimated-days-{{ $progressTask->id }}" type="number" min="1" max="90"
                                                    wire:model="estimatedDays" @disabled($hasEstimate)
                                                    x-on:input="estimate = $event.target.value.length > 0"
                                                    placeholder="Total hari"
                                                    aria-describedby="estimated-days-hint-{{ $progressTask->id }}"
                                                    class="{{ $estimateFieldClasses }} pr-14">
                                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-slate-400">hari</span>
                                            </div>
                                            @if ($hasEstimate)
                                                <p id="estimated-days-hint-{{ $progressTask->id }}" class="mt-1.5 flex items-start gap-1.5 text-xs text-emerald-800">
                                                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                    </svg>
                                                    <span>Estimasi sudah ditetapkan. Gunakan Update informasi untuk mengubahnya.</span>
                                                </p>
                                            @else
                                                <p id="estimated-days-hint-{{ $progressTask->id }}" class="mt-1.5 text-xs text-slate-500">Rentang 1–90 hari.</p>
                                            @endif
                                            @error('estimatedDays')
                                                <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label for="planned-start-{{ $progressTask->id }}" class="block text-sm font-semibold text-slate-700">
                                                Tanggal rencana mulai
                                            </label>
                                            <input id="planned-start-{{ $progressTask->id }}" type="date"
                                                wire:model="plannedStartOn" @disabled($hasEstimate)
                                                class="{{ $estimateFieldClasses }}">
                                            <p class="mt-1.5 text-xs text-slate-500">Kosongkan untuk memakai hari ini (WIB).</p>
                                            @error('plannedStartOn')
                                                <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- Langkah 2: rencana kegiatan --}}
                        <section class="rounded-xl border border-slate-200 bg-white p-4" aria-labelledby="risk-step-activity-{{ $progressTask->id }}">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold transition-colors"
                                    :class="activity >= 10 ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/20' : 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'">2</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <label id="risk-step-activity-{{ $progressTask->id }}" for="activity-plan-{{ $progressTask->id }}" class="text-sm font-bold text-slate-900">
                                            Rencana kegiatan <span class="text-rose-500" aria-hidden="true">*</span>
                                        </label>
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                            :class="activity >= 10 ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-500 ring-slate-200'">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            <span x-text="activity >= 10 ? 'Lengkap' : 'Perlu diisi'">{{ mb_strlen(trim($activityPlan)) >= 10 ? 'Lengkap' : 'Perlu diisi' }}</span>
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500">Langkah persiapan dan pelaksanaan yang akan dilakukan.</p>
                                    <textarea id="activity-plan-{{ $progressTask->id }}" rows="4" wire:model="activityPlan"
                                        x-on:input="activity = $event.target.value.length"
                                        required minlength="10" maxlength="2000" placeholder="Contoh: menyiapkan cadangan basis data, menonaktifkan layanan sementara, lalu menjalankan pembaruan..."
                                        aria-describedby="activity-plan-hint-{{ $progressTask->id }}"
                                        class="{{ $planFieldClasses }} {{ $planFieldStateClasses('activityPlan') }}"></textarea>
                                    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2 text-xs">
                                        <p id="activity-plan-hint-{{ $progressTask->id }}" class="text-slate-500">Minimal 10 karakter, maksimal 2.000 karakter.</p>
                                        <span class="shrink-0 font-medium tabular-nums" :class="activity > 2000 ? 'text-rose-600' : (activity >= 1800 ? 'text-amber-600' : 'text-slate-400')">
                                            <span x-text="activity">{{ mb_strlen((string) $activityPlan) }}</span>/2.000
                                        </span>
                                    </div>
                                    @error('activityPlan')
                                        <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </section>

                        {{-- Langkah 3: rencana perubahan --}}
                        <section class="rounded-xl border border-slate-200 bg-white p-4" aria-labelledby="risk-step-change-{{ $progressTask->id }}">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold transition-colors"
                                    :class="change >= 10 ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/20' : 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'">3</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <label id="risk-step-change-{{ $progressTask->id }}" for="change-plan-{{ $progressTask->id }}" class="text-sm font-bold text-slate-900">
                                            Rencana perubahan <span class="text-rose-500" aria-hidden="true">*</span>
                                        </label>
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                            :class="change >= 10 ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-500 ring-slate-200'">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            <span x-text="change >= 10 ? 'Lengkap' : 'Perlu diisi'">{{ mb_strlen(trim($changePlan)) >= 10 ? 'Lengkap' : 'Perlu diisi' }}</span>
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500">Perubahan apa yang akan diterapkan pada layanan.</p>
                                    <textarea id="change-plan-{{ $progressTask->id }}" rows="4" wire:model="changePlan"
                                        x-on:input="change = $event.target.value.length"
                                        required minlength="10" maxlength="2000" placeholder="Contoh: memperbarui konfigurasi layanan dan menyesuaikan parameter keamanan..."
                                        aria-describedby="change-plan-hint-{{ $progressTask->id }}"
                                        class="{{ $planFieldClasses }} {{ $planFieldStateClasses('changePlan') }}"></textarea>
                                    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2 text-xs">
                                        <p id="change-plan-hint-{{ $progressTask->id }}" class="text-slate-500">Minimal 10 karakter, maksimal 2.000 karakter.</p>
                                        <span class="shrink-0 font-medium tabular-nums" :class="change > 2000 ? 'text-rose-600' : (change >= 1800 ? 'text-amber-600' : 'text-slate-400')">
                                            <span x-text="change">{{ mb_strlen((string) $changePlan) }}</span>/2.000
                                        </span>
                                    </div>
                                    @error('changePlan')
                                        <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </section>

                        {{-- Langkah 4: mekanisme rollback --}}
                        <section class="rounded-xl border border-slate-200 bg-white p-4" aria-labelledby="risk-step-rollback-{{ $progressTask->id }}">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold transition-colors"
                                    :class="rollback >= 10 ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/20' : 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'">4</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <label id="risk-step-rollback-{{ $progressTask->id }}" for="rollback-mechanism-{{ $progressTask->id }}" class="text-sm font-bold text-slate-900">
                                            Mekanisme rollback <span class="text-rose-500" aria-hidden="true">*</span>
                                        </label>
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                            :class="rollback >= 10 ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-500 ring-slate-200'">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            <span x-text="rollback >= 10 ? 'Lengkap' : 'Perlu diisi'">{{ mb_strlen(trim($rollbackMechanism)) >= 10 ? 'Lengkap' : 'Perlu diisi' }}</span>
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500">Cara mengembalikan layanan bila pelaksanaan tidak berhasil.</p>
                                    <textarea id="rollback-mechanism-{{ $progressTask->id }}" rows="4" wire:model="rollbackMechanism"
                                        x-on:input="rollback = $event.target.value.length"
                                        required minlength="10" maxlength="2000" placeholder="Contoh: memulihkan cadangan basis data dan mengembalikan konfigurasi sebelumnya..."
                                        aria-describedby="rollback-mechanism-hint-{{ $progressTask->id }}"
                                        class="{{ $planFieldClasses }} {{ $planFieldStateClasses('rollbackMechanism') }}"></textarea>
                                    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2 text-xs">
                                        <p id="rollback-mechanism-hint-{{ $progressTask->id }}" class="text-slate-500">Minimal 10 karakter, maksimal 2.000 karakter.</p>
                                        <span class="shrink-0 font-medium tabular-nums" :class="rollback > 2000 ? 'text-rose-600' : (rollback >= 1800 ? 'text-amber-600' : 'text-slate-400')">
                                            <span x-text="rollback">{{ mb_strlen((string) $rollbackMechanism) }}</span>/2.000
                                        </span>
                                    </div>
                                    @error('rollbackMechanism')
                                        <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </section>
                    </div>

                    {{-- Footer aksi --}}
                    <div class="sticky bottom-0 z-20 rounded-b-2xl border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:px-5">
                        @error('progress')
                            <p class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-800" role="alert">
                                {{ $message }}
                            </p>
                        @enderror
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="order-2 text-center text-xs text-slate-500 sm:order-1 sm:text-left">
                                Semua langkah wajib diisi sebelum pengerjaan dimulai.
                            </p>
                            <div class="order-1 flex flex-col gap-2 sm:order-2 sm:flex-row sm:items-center">
                                @if ($hasRiskAnalysis)
                                    <button type="button" wire:click="toggleRiskForm"
                                        class="inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 sm:w-auto">
                                        Batal
                                    </button>
                                @endif
                                <button type="submit" wire:loading.attr="disabled" wire:target="saveRiskAnalysis"
                                    class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-emerald-400 sm:w-auto">
                                    <svg wire:loading wire:target="saveRiskAnalysis" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="saveRiskAnalysis">Simpan Analisis Risiko</span>
                                    <span wire:loading wire:target="saveRiskAnalysis">Menyimpan…</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            @endif

            @if (! empty($scheduleConflicts))
                <div wire:key="estimate-conflicts-{{ $progressTask->id }}"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-900">Jadwal berpotensi bentrok</p>
                    <ul class="mt-2 space-y-1 text-xs text-amber-800">
                        @foreach ($scheduleConflicts as $conflict)
                            <li>Aplikasi ini juga terjadwal di {{ $conflict['identifier'] }} pada {{ $conflict['range'] }}.</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($progressTask->execution_status === 'pending')
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm font-semibold text-blue-900">Siap memulai pengerjaan?</p>
                    <p class="mt-1 text-xs text-blue-800">
                        Pastikan analisis risiko sudah sesuai. Setelah dimulai, target selesai dihitung otomatis dari estimasi.
                    </p>
                    <button type="button" wire:click="startWork" wire:loading.attr="disabled" wire:target="startWork"
                        @disabled(! $canStart)
                        class="mt-3 inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-300">
                        <span wire:loading.remove wire:target="startWork">Mulai Pengerjaan</span>
                        <span wire:loading wire:target="startWork">Memulai…</span>
                    </button>
                    @unless ($canStart)
                        <p class="mt-2 text-xs text-slate-600">Isi form analisis risiko terlebih dahulu.</p>
                    @endunless
                </div>
            @endif

            @if ($hasEstimate)
                <div wire:key="update-information-{{ $progressTask->id }}"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-amber-900">Update informasi</p>
                            <p class="mt-1 text-xs text-amber-800">Perbarui estimasi hari bila jadwal pengerjaan berubah.</p>
                        </div>
                        <button type="button" wire:click="$toggle('showUpdateForm')"
                            x-bind:aria-expanded="@js($showUpdateForm)"
                            aria-controls="update-information-form-{{ $progressTask->id }}"
                            class="inline-flex min-h-10 items-center justify-center rounded-lg border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-amber-800 shadow-sm transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2">
                            {{ $showUpdateForm ? 'Tutup' : 'Update' }}
                        </button>
                    </div>

                    @if ($showUpdateForm)
                        <form id="update-information-form-{{ $progressTask->id }}" wire:submit="reviseEstimate"
                            wire:key="update-information-form-{{ $progressTask->id }}"
                            class="mt-4 grid grid-cols-1 gap-3 border-t border-amber-200 pt-4 sm:grid-cols-2">
                            <div>
                                <label for="revised-days-{{ $progressTask->id }}"
                                    class="block text-sm font-semibold text-slate-700">Estimasi hari baru</label>
                                <input id="revised-days-{{ $progressTask->id }}" type="number" min="1"
                                    max="90" wire:model="revisedDays" required placeholder="Total hari baru"
                                    class="mt-1 block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500">
                                <p class="mt-1 text-xs text-slate-500">Rentang 1–90 hari.</p>
                                @error('revisedDays')
                                    <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="update-reason-{{ $progressTask->id }}"
                                    class="block text-sm font-semibold text-slate-700">Alasan pembaruan (wajib, min. 10 karakter)</label>
                                <textarea id="update-reason-{{ $progressTask->id }}" rows="2"
                                    wire:model="revisionReason" required minlength="10" maxlength="500"
                                    placeholder="Tulis alasan pembaruan informasi"
                                    class="mt-1 block w-full resize-y rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500"></textarea>
                                <div class="mt-1 flex items-center justify-between gap-2 text-xs text-slate-500">
                                    <span>Minimal 10 karakter.</span>
                                    <span><span x-text="String($wire.revisionReason ?? '').length">0</span>/500</span>
                                </div>
                                @error('revisionReason')
                                    <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="sm:col-span-2 flex justify-end">
                                <button type="submit" wire:loading.attr="disabled" wire:target="reviseEstimate"
                                    class="inline-flex min-h-10 items-center justify-center rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-amber-400">
                                    <span wire:loading.remove wire:target="reviseEstimate">Simpan Update</span>
                                    <span wire:loading wire:target="reviseEstimate">Menyimpan…</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endif

            @if ($progressTask->execution_status === 'in_progress')
                <div wire:key="change-plan-note-{{ $progressTask->id }}"
                    class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm font-semibold text-blue-900">Rencana perubahan</p>
                    <p class="mt-1 text-xs text-blue-800">Perbarui rencana perubahan dari analisis risiko bila ada penyesuaian selama pengerjaan berlangsung.</p>

                    <form wire:submit="addProgressNote" wire:key="change-plan-note-form-{{ $progressTask->id }}"
                        class="mt-3 space-y-3">
                        <div>
                            <label for="progress-note-{{ $progressTask->id }}"
                                class="block text-sm font-semibold text-slate-700">Rencana perubahan</label>
                            <textarea id="progress-note-{{ $progressTask->id }}" rows="3"
                                wire:model="progressNote" required minlength="5" maxlength="1000"
                                placeholder="Tulis pembaruan rencana perubahan"
                                class="mt-1 block w-full resize-y rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"></textarea>
                            <div class="mt-1 flex items-center justify-end text-xs text-slate-500">
                                <span><span x-text="String($wire.progressNote ?? '').length">0</span>/1000</span>
                            </div>
                            @error('progressNote')
                                <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 focus-within:ring-2 focus-within:ring-amber-600 focus-within:ring-offset-2">
                            <span class="text-sm font-medium text-amber-900">Tandai sebagai kendala</span>
                            <span class="relative inline-flex shrink-0 items-center">
                                <input type="checkbox" wire:model="progressBlocked" class="peer sr-only">
                                <span class="h-6 w-11 rounded-full bg-slate-300 transition-colors peer-checked:bg-amber-500 peer-focus-visible:ring-2 peer-focus-visible:ring-amber-600 peer-focus-visible:ring-offset-2" aria-hidden="true"></span>
                                <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5" aria-hidden="true"></span>
                            </span>
                        </label>

                        <button type="submit" wire:loading.attr="disabled" wire:target="addProgressNote"
                            class="inline-flex min-h-10 items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-blue-400">
                            <span wire:loading.remove wire:target="addProgressNote">Tambah Rencana Perubahan</span>
                            <span wire:loading wire:target="addProgressNote">Menyimpan…</span>
                        </button>
                    </form>
                </div>
            @endif
        </div>
    @endif

    @if ($progressTask->updates->isNotEmpty())
        <div class="mt-5 border-t border-slate-100 pt-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Riwayat Progres</p>
            <ol class="mt-4">
                @foreach ($progressTask->updates as $update)
                    @php
                        $updateMeta = match ($update->type) {
                            'risk_analysis_submitted' => [
                                'label' => 'Analisis risiko disimpan',
                                'classes' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                                'icon' => 'shield',
                            ],
                            'estimate_set' => [
                                'label' => 'Estimasi ditetapkan',
                                'classes' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                                'icon' => 'calendar',
                            ],
                            'started' => [
                                'label' => 'Pengerjaan dimulai',
                                'classes' => 'bg-blue-100 text-blue-700 ring-blue-200',
                                'icon' => 'play',
                            ],
                            'progress' => [
                                'label' => 'Rencana perubahan',
                                'classes' => 'bg-slate-100 text-slate-600 ring-slate-200',
                                'icon' => 'note',
                            ],
                            'blocked' => [
                                'label' => 'Kendala',
                                'classes' => 'bg-amber-100 text-amber-800 ring-amber-200',
                                'icon' => 'warning',
                            ],
                            'estimate_revised' => [
                                'label' => 'Update informasi',
                                'classes' => 'bg-amber-100 text-amber-800 ring-amber-200',
                                'icon' => 'clock',
                            ],
                            default => [
                                'label' => $update->type,
                                'classes' => 'bg-slate-100 text-slate-600 ring-slate-200',
                                'icon' => 'note',
                            ],
                        };
                    @endphp
                    <li wire:key="release-task-update-{{ $update->id }}"
                        class="relative flex gap-3 pb-5 last:pb-0">
                        @unless ($loop->last)
                            <span class="absolute bottom-0 left-4 top-9 w-px -translate-x-1/2 bg-slate-200" aria-hidden="true"></span>
                        @endunless

                        <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-1 ring-inset {{ $updateMeta['classes'] }}">
                            @switch($updateMeta['icon'])
                                @case('shield')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v5.25c0 4.5 3.15 8.4 7.5 9.75 4.35-1.35 7.5-5.25 7.5-9.75V6L12 3Z" />
                                    </svg>
                                    @break
                                @case('calendar')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    @break
                                @case('play')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    @break
                                @case('warning')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3h.008v.008H12v-.008ZM10.34 3.94l-7.5 13a1.5 1.5 0 0 0 1.3 2.25h15a1.5 1.5 0 0 0 1.3-2.25l-7.5-13a1.5 1.5 0 0 0-2.6 0Z" />
                                    </svg>
                                    @break
                                @case('clock')
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    @break
                                @default
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                    </svg>
                            @endswitch
                        </span>

                        <div class="min-w-0 flex-1 rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                                <p class="font-semibold text-slate-800">{{ $updateMeta['label'] }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $update->created_at?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') }} WIB
                                </p>
                            </div>
                            @if ($update->new_estimated_days !== null)
                                <p class="mt-1 text-xs font-medium text-slate-600">
                                    @if ($update->old_estimated_days !== null)
                                        {{ $update->old_estimated_days }} hari &rarr; {{ $update->new_estimated_days }} hari
                                    @else
                                        Estimasi: {{ $update->new_estimated_days }} hari
                                    @endif
                                </p>
                            @endif
                            @if (filled($update->reason))
                                <p class="mt-1 text-xs text-slate-600">Alasan: {{ $update->reason }}</p>
                            @endif
                            @if (filled($update->note))
                                <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-700">{{ $update->note }}</p>
                            @endif
                            <p class="mt-1 text-xs text-slate-500">{{ $update->user?->name ?? 'Tim Rilis' }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
@endif
</div>
