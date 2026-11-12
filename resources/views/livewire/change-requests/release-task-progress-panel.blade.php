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
        'rolled_back' => ['label' => 'Rollback', 'classes' => 'bg-rose-100 text-rose-700 ring-rose-200'],
        default => ['label' => $progressTask->execution_status, 'classes' => 'bg-slate-100 text-slate-700 ring-slate-200'],
    };
    $progressStatusLabel = $progressStatusBadge['label'];
    $progressEditable = in_array($progressTask->execution_status, ['pending', 'in_progress'], true)
        && ! $rfc->trashed();

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
            'label' => 'Estimasi',
            'state' => $progressTask->estimated_days !== null ? 'done' : 'active',
        ],
        [
            'label' => 'Dikerjakan',
            'state' => match (true) {
                in_array($progressTask->execution_status, ['successful', 'rolled_back'], true) => 'done',
                $progressTask->execution_status === 'in_progress' => 'active',
                $progressTask->estimated_days !== null => 'active',
                default => 'upcoming',
            },
        ],
        [
            'label' => 'Selesai / Rollback',
            'state' => in_array($progressTask->execution_status, ['successful', 'rolled_back'], true)
                ? 'done'
                : 'upcoming',
        ],
    ];
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
                <p class="text-xs text-slate-500">Estimasi, pelaksanaan, dan riwayat tugas Anda.</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $progressStatusBadge['classes'] }}">
            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
            {{ $progressStatusLabel }}
        </span>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status</p>
            <p class="mt-1 font-semibold text-slate-800">{{ $progressStatusLabel }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Estimasi</p>
            <p class="mt-1 font-semibold text-slate-800">
                {{ $progressTask->estimated_days ? $progressTask->estimated_days.' hari' : 'Belum ditetapkan' }}
            </p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Mulai (WIB)</p>
            <p class="mt-1 font-semibold text-slate-800">
                {{ $progressTask->scheduled_start
                    ? $progressTask->scheduled_start->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i')
                    : '—' }}
            </p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Target selesai (WIB)</p>
            <p class="mt-1 font-semibold text-slate-800">
                {{ $progressTask->scheduled_end
                    ? $progressTask->scheduled_end->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i')
                    : '—' }}
            </p>
            @if ($progressCountdown !== null)
                <span class="mt-2 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $progressCountdown['classes'] }}">
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
                <span class="relative z-10 mt-2 bg-white px-1 text-[11px] font-semibold leading-tight sm:text-xs {{ $stepLabelClasses }}">
                    {{ $step['label'] }}
                </span>
            </li>
        @endforeach
    </ol>

    @if (filled($progressMessage))
        <p class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
            {{ $progressMessage }}
        </p>
    @endif

    @error('progress')
        <p class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-800">
            {{ $message }}
        </p>
    @enderror

    @if ($progressTask->execution_status === 'pending')
        <p class="mt-4 text-xs text-slate-500">
            Tetapkan estimasi dan klik Mulai Pengerjaan untuk dapat mencatat hasil implementasi.
        </p>
    @endif

    @if ($progressEditable)
        <div class="mt-5 space-y-4 border-t border-slate-100 pt-5">
            @if ($progressTask->execution_status === 'in_progress')
                @can('implement', $rfc)
                    <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 sm:p-5">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <h4 class="text-base font-bold text-emerald-900">Selesaikan pekerjaan</h4>
                                <p class="mt-1 text-sm text-emerald-800">
                                    Isi laporan hasil. Setelah dikirim, RFC masuk review Change Manager.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="flex flex-col gap-1.5">
                                <a href="{{ route('change-requests.implement', ['rfc' => $rfc, 'hasil' => 'success']) }}" wire:navigate
                                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">
                                    Selesaikan Pekerjaan
                                </a>
                                <p class="text-xs text-slate-600">Isi laporan hasil pengerjaan.</p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <a href="{{ route('change-requests.implement', ['rfc' => $rfc, 'hasil' => 'rolled_back']) }}" wire:navigate
                                    class="inline-flex items-center justify-center rounded-lg border border-rose-300 bg-white px-4 py-3 text-sm font-semibold text-rose-700 shadow-sm transition hover:border-rose-400 hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:ring-offset-2">
                                    Rollback
                                </a>
                                <p class="text-xs font-medium text-rose-700">Wajib menuliskan alasan rollback.</p>
                            </div>
                        </div>
                    </div>
                @endcan
            @endif

            @if ($progressTask->estimated_days === null)
                <form wire:submit="setEstimate" wire:key="estimate-form-{{ $progressTask->id }}"
                    x-data="{
                        days: @js((string) $estimatedDays),
                        start: @js($plannedStartOn),
                        setDays(value) {
                            const next = String(Math.min(90, Math.max(1, parseInt(value) || 1)));
                            this.days = next;
                            this.$refs.daysInput.value = next;
                            this.$refs.daysInput.dispatchEvent(new Event('input', { bubbles: true }));
                        },
                        get finishLabel() {
                            const total = parseInt(this.days);
                            if (!total || total < 1) return '—';
                            const base = this.start ? new Date(this.start + 'T00:00:00') : new Date();
                            base.setDate(base.getDate() + total);
                            return base.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
                        }
                    }"
                    class="min-h-[7rem] rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div>
                            <label for="estimated-days-{{ $progressTask->id }}"
                                class="block text-sm font-semibold text-slate-700">Estimasi pengerjaan (hari)</label>
                            <div class="mt-1 flex items-stretch gap-2">
                                <button type="button" x-on:click="setDays((parseInt(days) || 0) - 1)"
                                    aria-label="Kurangi estimasi"
                                    class="inline-flex w-11 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-lg font-bold text-slate-600 shadow-sm transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                                    &minus;
                                </button>
                                <input x-ref="daysInput" id="estimated-days-{{ $progressTask->id }}" type="number"
                                    min="1" max="90" wire:model="estimatedDays" x-on:input="days = $event.target.value"
                                    required placeholder="Total hari"
                                    class="block w-full rounded-lg border-slate-300 bg-white text-center text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500">
                                <button type="button" x-on:click="setDays((parseInt(days) || 0) + 1)"
                                    aria-label="Tambah estimasi"
                                    class="inline-flex w-11 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-lg font-bold text-slate-600 shadow-sm transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                                    +
                                </button>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ([1, 3, 5, 7, 14] as $quickDays)
                                    <button type="button" x-on:click="setDays({{ $quickDays }})"
                                        class="rounded-full border border-emerald-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                                        {{ $quickDays }} hari
                                    </button>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Rentang 1–90 hari.</p>
                            @error('estimatedDays')
                                <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="planned-start-{{ $progressTask->id }}"
                                class="block text-sm font-semibold text-slate-700">Rencana mulai (opsional)</label>
                            <input id="planned-start-{{ $progressTask->id }}" type="date"
                                wire:model="plannedStartOn" x-on:input="start = $event.target.value"
                                class="mt-1 block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500">
                            <p class="mt-1 text-xs text-slate-500">Kosongkan untuk memakai hari ini (WIB).</p>
                            <p class="mt-2 text-sm font-semibold text-emerald-700">
                                Perkiraan selesai: <span x-text="finishLabel">—</span>
                            </p>
                            @error('plannedStartOn')
                                <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="submit" wire:loading.attr="disabled" wire:target="setEstimate"
                            class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-emerald-400">
                            <span wire:loading.remove wire:target="setEstimate">Simpan Estimasi</span>
                            <span wire:loading wire:target="setEstimate">Menyimpan…</span>
                        </button>
                    </div>
                </form>
            @else
                @php
                    $summaryWindow = $progressTask->scheduleWindow();
                @endphp
                <div wire:key="estimate-summary-{{ $progressTask->id }}"
                    class="flex min-h-[7rem] flex-col justify-center rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-emerald-900">Estimasi pengerjaan</p>
                            <p class="mt-1 text-sm text-emerald-800">
                                {{ $progressTask->estimated_days }} hari
                                @if ($progressTask->planned_start_on !== null)
                                    &middot; rencana mulai
                                    {{ $progressTask->planned_start_on->copy()->locale('id')->translatedFormat('d M Y') }}
                                @endif
                                @if ($summaryWindow !== null)
                                    &middot; perkiraan selesai
                                    {{ $summaryWindow[1]->copy()->locale('id')->translatedFormat('d M Y') }}
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">
                            Tersimpan
                        </span>
                    </div>
                </div>
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
                        Pastikan estimasi sudah sesuai sebelum memulai. Setelah dimulai, target selesai dihitung otomatis dari estimasi.
                    </p>
                    <button type="button" wire:click="startWork" wire:loading.attr="disabled" wire:target="startWork"
                        @disabled($progressTask->estimated_days === null)
                        class="mt-3 inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-300">
                        <span wire:loading.remove wire:target="startWork">Mulai Pengerjaan</span>
                        <span wire:loading wire:target="startWork">Memulai…</span>
                    </button>
                    @if ($progressTask->estimated_days === null)
                        <p class="mt-2 text-xs text-slate-600">Tetapkan estimasi terlebih dahulu untuk memulai.</p>
                    @endif
                </div>
            @endif

            @if ($progressTask->estimated_days !== null)
                <div wire:key="revision-estimate-{{ $progressTask->id }}"
                    class="overflow-hidden rounded-xl border border-amber-200 bg-amber-50" x-data="{ open: false }">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open"
                        aria-controls="revisi-estimasi-{{ $progressTask->id }}"
                        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-600">
                        <span class="flex items-center gap-2 text-sm font-semibold text-amber-900">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            Revisi Estimasi
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-amber-700 transition-transform" x-bind:class="open ? 'rotate-180' : ''"
                            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <form id="revisi-estimasi-{{ $progressTask->id }}" x-show="open" style="display: none;"
                        wire:submit="reviseEstimate" wire:key="revision-form-{{ $progressTask->id }}"
                        class="space-y-3 border-t border-amber-200 bg-white px-4 py-4">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="revised-days-{{ $progressTask->id }}"
                                    class="block text-sm font-semibold text-slate-700">Total hari baru</label>
                                <input id="revised-days-{{ $progressTask->id }}" type="number" min="1"
                                    max="90" wire:model="revisedDays" required placeholder="Total hari baru"
                                    class="mt-1 block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500">
                                <p class="mt-1 text-xs text-slate-500">Rentang 1–90 hari.</p>
                                @error('revisedDays')
                                    <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="revision-reason-{{ $progressTask->id }}"
                                    class="block text-sm font-semibold text-slate-700">Alasan revisi (wajib, min. 10 karakter)</label>
                                <textarea id="revision-reason-{{ $progressTask->id }}" rows="2"
                                    wire:model="revisionReason" required minlength="10" maxlength="500"
                                    placeholder="Alasan revisi (wajib, min. 10 karakter)"
                                    class="mt-1 block w-full resize-y rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500"></textarea>
                                <div class="mt-1 flex items-center justify-between gap-2 text-xs text-slate-500">
                                    <span>Minimal 10 karakter.</span>
                                    <span><span x-text="String($wire.revisionReason ?? '').length">0</span>/500</span>
                                </div>
                                @error('revisionReason')
                                    <p class="mt-1 text-xs text-rose-600" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="reviseEstimate"
                            class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-amber-400">
                            <span wire:loading.remove wire:target="reviseEstimate">Simpan Revisi</span>
                            <span wire:loading wire:target="reviseEstimate">Menyimpan…</span>
                        </button>
                    </form>
                </div>
            @endif

            @if ($progressTask->execution_status === 'in_progress')
                <div wire:key="progress-note-{{ $progressTask->id }}"
                    class="overflow-hidden rounded-xl border border-blue-200 bg-blue-50" x-data="{ open: false }">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open"
                        aria-controls="catatan-kegiatan-{{ $progressTask->id }}"
                        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-600">
                        <span class="flex items-center gap-2 text-sm font-semibold text-blue-900">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                            </svg>
                            Catatan Kegiatan
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-blue-700 transition-transform" x-bind:class="open ? 'rotate-180' : ''"
                            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <form id="catatan-kegiatan-{{ $progressTask->id }}" x-show="open" style="display: none;"
                        wire:submit="addProgressNote" wire:key="progress-note-form-{{ $progressTask->id }}"
                        class="space-y-3 border-t border-blue-200 bg-white px-4 py-4">
                        <div>
                            <label for="progress-note-{{ $progressTask->id }}"
                                class="block text-sm font-semibold text-slate-700">Catatan kegiatan</label>
                            <textarea id="progress-note-{{ $progressTask->id }}" rows="3"
                                wire:model="progressNote" required minlength="5" maxlength="1000"
                                placeholder="Tulis kegiatan terbaru"
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
                            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-blue-400">
                            <span wire:loading.remove wire:target="addProgressNote">Tambah Catatan</span>
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
                                'label' => 'Catatan kegiatan',
                                'classes' => 'bg-slate-100 text-slate-600 ring-slate-200',
                                'icon' => 'note',
                            ],
                            'blocked' => [
                                'label' => 'Kendala',
                                'classes' => 'bg-amber-100 text-amber-800 ring-amber-200',
                                'icon' => 'warning',
                            ],
                            'estimate_revised' => [
                                'label' => 'Estimasi direvisi',
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
                                <p class="text-[11px] text-slate-500">
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
                            <p class="mt-1 text-[11px] text-slate-500">{{ $update->user?->name ?? 'Tim Rilis' }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
@endif
</div>
