<div class="space-y-5 sm:space-y-6" wire:poll.60s.visible>

    {{-- Header --}}
    <x-dashboard.hero-banner class="dashboard-rise" title="Tugas saya" :user-name="$user->name"
        role-label="Tim Rilis" :description="$teamName.' · Antrean pengerjaan RFC yang ditugaskan kepadamu.'"
        :date-time="$dateTime" />

    {{-- Kartu ringkasan --}}
    <section aria-labelledby="metrik-tim-rilis" class="dashboard-rise dashboard-rise-1">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 h-10 w-1 rounded-full bg-gradient-to-b from-blue-500 via-sky-400 to-amber-300"
                    aria-hidden="true"></span>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-blue-600">Ikhtisar Tugas</p>
                    <h2 id="metrik-tim-rilis" class="mt-0.5 text-lg font-bold tracking-tight text-slate-900">
                        Ringkasan Pengerjaan
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">Posisi tugas Tim Rilis per {{ $dateTime }} WIB.</p>
                </div>
            </div>

            @if ($overview['counts']['needs_action'] > 0)
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 shadow-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500" aria-hidden="true"></span>
                    {{ number_format($overview['counts']['needs_action'], 0, ',', '.') }} perlu tindakan hari ini
                </span>
            @endif
        </div>

        <div class="grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-dashboard.stat-card label="Menunggu dimulai" :value="$overview['counts']['pending']"
                hint="Tugas yang belum mulai dikerjakan" tone="blue">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Sedang dikerjakan" :value="$overview['counts']['doing']"
                hint="Pengerjaan berjalan sesuai atau tanpa target" tone="indigo">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M11.42 6.42a4.5 4.5 0 0 0 6.16 6.16l3.17 3.17a2.25 2.25 0 0 1-3.18 3.18l-3.17-3.17a4.5 4.5 0 0 1-6.16-6.16l2.12 2.12 3.18-3.18-2.12-2.12Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Melewati estimasi" :value="$overview['counts']['late']"
                hint="Perlu segera diselesaikan" :tone="$overview['counts']['late'] > 0 ? 'rose' : 'slate'">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M12 9v4m0 4h.01M10.29 3.86 2.82 17a1.5 1.5 0 0 0 1.3 2.25h15.76a1.5 1.5 0 0 0 1.3-2.25L13.71 3.86a1.5 1.5 0 0 0-2.42 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Selesai bulan ini" :value="$overview['counts']['done_this_month']"
                hint="Berhasil atau di-rollback bulan ini" tone="emerald">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="m9 12.75 2.25 2.25L15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>
        </div>
    </section>

    {{-- Chip filter --}}
    <div class="dashboard-rise dashboard-rise-2 flex flex-wrap items-center gap-2" role="group"
        aria-label="Filter daftar tugas">
        @foreach ([
            'all' => ['label' => 'Semua', 'count' => $filterCounts['all']],
            'late' => ['label' => 'Terlambat', 'count' => $filterCounts['late']],
            'doing' => ['label' => 'Dikerjakan', 'count' => $filterCounts['doing']],
            'pending' => ['label' => 'Belum dimulai', 'count' => $filterCounts['pending']],
        ] as $key => $chip)
            <button type="button" wire:click="$set('filter', '{{ $key }}')" wire:loading.attr="disabled"
                @if ($filter === $key) aria-pressed="true" @else aria-pressed="false" @endif
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200 disabled:cursor-wait {{ $filter === $key ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700' }}">
                {{ $chip['label'] }}
                <span
                    class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] tabular-nums {{ $filter === $key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">
                    {{ number_format($chip['count'], 0, ',', '.') }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- Daftar tugas --}}
    <x-dashboard.panel class="dashboard-rise dashboard-rise-3" title="Daftar Tugas" id="daftar-tugas-tim-rilis"
        description="Baris tugas aktif, diurutkan berdasarkan prioritas tindakan.">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M8.25 6.75h9M8.25 12h9M8.25 17.25h9M4.5 6.75h.008v.008H4.5V6.75Zm0 5.25h.008v.008H4.5V12Zm0 5.25h.008v.008H4.5v-.008Z" />
            </svg>
        </x-slot:icon>

        @if (filled($actionError))
            <div class="border-b border-rose-100 bg-rose-50 px-5 py-3" role="alert">
                <p class="text-xs font-semibold text-rose-700">{{ $actionError }}</p>
            </div>
        @endif

        @if ($visibleTasks === [])
            <div class="p-5">
                @if ($overview['tasks'] === [])
                    <x-dashboard.empty-state title="Belum ada tugas"
                        description="Tugas baru dari Change Manager akan muncul di sini." />
                @else
                    <x-dashboard.empty-state title="Tidak ada tugas pada filter ini"
                        description="Pilih filter lain untuk melihat tugas yang tersedia." />
                @endif
            </div>
        @else
            <ul class="divide-y divide-slate-100/80">
                @foreach ($visibleTasks as $task)
                    @php
                        $state = $task['state'];
                        $estimate = (int) ($task['estimated_days'] ?? 0);
                        $elapsed = (int) $task['days_elapsed'];
                        $percent = $state === 'late'
                            ? 100
                            : ($estimate > 0 ? min(100, (int) round(($elapsed / $estimate) * 100)) : 0);
                        $progressLabel = match (true) {
                            in_array($state, ['late', 'doing'], true) && $estimate > 0 => 'Hari ke-'.($elapsed + 1).' dari '.$estimate,
                            $task['has_estimate'] => 'Estimasi '.$estimate.' hari',
                            default => 'Estimasi pengerjaan belum diisi',
                        };
                    @endphp

                    <li wire:key="tugas-tim-rilis-{{ $task['task_id'] }}"
                        class="p-5 transition-colors duration-200 hover:bg-blue-50/30">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-6">
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-lg border border-blue-100 bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-800">
                                        {{ $task['rfc_number'] ?: 'RFC' }}
                                    </span>

                                    @if ($state === 'late')
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[10px] font-bold text-rose-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"
                                                aria-hidden="true"></span>
                                            Terlambat {{ $task['days_overdue'] }} hari
                                        </span>
                                    @elseif ($state === 'doing')
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"
                                                aria-hidden="true"></span>
                                            Sedang dikerjakan
                                        </span>
                                    @elseif (! $task['has_estimate'])
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"
                                                aria-hidden="true"></span>
                                            Estimasi belum diisi
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold text-slate-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"
                                                aria-hidden="true"></span>
                                            Siap dimulai
                                        </span>
                                    @endif
                                </div>

                                <p class="line-clamp-2 text-sm font-semibold leading-snug text-slate-800">
                                    {{ $task['title'] }}
                                </p>

                                <p class="truncate text-xs text-slate-500">
                                    {{ $task['application_name'] ?: 'Aplikasi tidak tercatat' }}
                                    <span class="text-slate-300" aria-hidden="true">·</span>
                                    {{ $task['requester_unit'] ?: 'OPD pemohon tidak tercatat' }}
                                </p>

                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between gap-3 text-[11px] font-medium">
                                        <span class="{{ $state === 'late' ? 'text-rose-700' : 'text-slate-500' }}">
                                            {{ $progressLabel }}
                                        </span>
                                        @if ($state === 'late' && $estimate > 0)
                                            <span class="tabular-nums text-rose-600">{{ $estimate }} hari
                                                terlewati</span>
                                        @endif
                                    </div>
                                    <div
                                        class="h-2 w-full overflow-hidden rounded-full {{ $state === 'late' ? 'bg-rose-100' : 'bg-slate-100' }}">
                                        <div class="h-full rounded-full transition-all duration-300 {{ $state === 'late' ? 'bg-rose-500' : 'bg-gradient-to-r from-blue-500 to-sky-400' }}"
                                            style="width: {{ $percent }}%"></div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 lg:w-44 lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                                <p class="text-[11px] font-medium text-slate-500">
                                    @if ($task['target_at'] !== null)
                                        target {{ $task['target_at']->copy()->locale('id')->translatedFormat('j M') }}
                                    @else
                                        target belum ditetapkan
                                    @endif
                                </p>

                                @if ($task['primary_action'] === 'finish')
                                    <a href="{{ route('change-requests.implement', ['rfc' => $task['rfc_id'], 'hasil' => 'success']) }}"
                                        wire:navigate
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-rose-600 to-orange-500 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-rose-500/20 transition duration-200 hover:-translate-y-0.5 hover:from-rose-700 hover:to-orange-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-rose-200">
                                        Selesaikan
                                    </a>
                                @elseif ($task['primary_action'] === 'open')
                                    <a href="{{ route('change-requests.show', ['rfc' => $task['rfc_id']]) }}#progres-pengerjaan"
                                        wire:navigate
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-blue-600 to-sky-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-blue-500/20 transition duration-200 hover:-translate-y-0.5 hover:from-blue-700 hover:to-sky-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200">
                                        Buka
                                    </a>
                                @elseif ($task['primary_action'] === 'set_estimate')
                                    <a href="{{ route('change-requests.show', ['rfc' => $task['rfc_id']]) }}#progres-pengerjaan"
                                        wire:navigate
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-amber-300 hover:bg-amber-100 focus:outline-none focus-visible:ring-4 focus-visible:ring-amber-200">
                                        Isi estimasi
                                    </a>
                                @else
                                    <button type="button" wire:click="startTask({{ $task['task_id'] }})"
                                        wire:confirm="Mulai pengerjaan RFC {{ $task['rfc_number'] }}? Status RFC akan menjadi Sedang Dikerjakan."
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-emerald-600 to-teal-500 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-emerald-500/20 transition duration-200 hover:-translate-y-0.5 hover:from-emerald-700 hover:to-teal-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-200">
                                        Mulai
                                    </button>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div
            class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3">
            <p class="text-[11px] text-slate-500">Diurutkan: terlambat, sedang dikerjakan, lalu belum dimulai.</p>
            <a href="{{ route('change-requests.index') }}" wire:navigate
                class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-blue-600 transition hover:text-blue-700">
                Lihat riwayat tugas
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                </svg>
            </a>
        </div>
    </x-dashboard.panel>
</div>
