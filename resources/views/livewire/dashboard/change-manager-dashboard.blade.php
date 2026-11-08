<div class="space-y-5 sm:space-y-6">

    @php
        $metricTotal = max(0, (int) ($overview['total'] ?? 0));
        $metricPercent = fn (int $value): float => $metricTotal > 0 ? ($value / $metricTotal) * 100 : 0.0;
    @endphp

    {{-- Banner sambutan --}}
    <x-dashboard.hero-banner class="dashboard-rise" title="Dashboard Change Manager" :user-name="$user->name"
        role-label="Change Manager" :show-meta="false" />

    {{-- Enam kartu metrik --}}
    <section aria-labelledby="metrik-change-manager" class="dashboard-rise dashboard-rise-1">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 h-10 w-1 rounded-full bg-gradient-to-b from-blue-500 via-sky-400 to-amber-300"
                    aria-hidden="true"></span>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-blue-600">Ikhtisar Dashboard</p>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2">
                        <h2 id="metrik-change-manager" class="text-lg font-bold tracking-tight text-slate-900">
                            Ringkasan Layanan
                        </h2>
                        @if (($overview['satisfaction']['count'] ?? 0) > 0)
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-800 shadow-sm">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                Kepuasan {{ number_format($overview['satisfaction']['average'], 1, ',', '.') }} / 5
                                &middot; {{ $overview['satisfaction']['count'] }} penilaian
                            </span>
                        @else
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold text-slate-500 shadow-sm">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-300" aria-hidden="true"></span>
                                Belum ada penilaian
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Posisi RFC yang menjadi tanggung jawab Change Manager.</p>
                </div>
            </div>
        </div>

        <div class="grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-dashboard.stat-card label="Menunggu Review" :value="$overview['pending_review']"
                :percent="$metricPercent($overview['pending_review'])" hint="Perlu penilaian risiko dan keputusan"
                tone="blue">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9 5.25h6M9 5.25V3.75h6v1.5M9 5.25H6.75A1.5 1.5 0 0 0 5.25 6.75v13.5a1.5 1.5 0 0 0 1.5 1.5h10.5a1.5 1.5 0 0 0 1.5-1.5V6.75a1.5 1.5 0 0 0-1.5-1.5H15M8.25 12l2.25 2.25 5.25-5.25" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Disetujui" :value="$overview['approved']"
                :percent="$metricPercent($overview['approved'])" hint="Siap memulai implementasi" tone="emerald">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Sedang Dikerjakan" :value="$overview['in_progress']"
                :percent="$metricPercent($overview['in_progress'])" hint="Implementasi sedang berjalan"
                tone="indigo">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M11.42 6.42a4.5 4.5 0 0 0 6.16 6.16l3.17 3.17a2.25 2.25 0 0 1-3.18 3.18l-3.17-3.17a4.5 4.5 0 0 1-6.16-6.16l2.12 2.12 3.18-3.18-2.12-2.12Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Menunggu Evaluasi" :value="$overview['waiting_evaluation']"
                :percent="$metricPercent($overview['waiting_evaluation'])" hint="Perlu evaluasi dan penutupan"
                tone="amber">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M12 8.25v3.75l2.25 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Ditutup" :value="$overview['closed']"
                :percent="$metricPercent($overview['closed'])" hint="RFC telah ditutup" tone="slate">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0 1 19.5 12.75v6A2.25 2.25 0 0 1 17.25 21H6.75a2.25 2.25 0 0 1-2.25-2.25v-6a2.25 2.25 0 0 1 2.25-2.25Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Ditolak" :value="$overview['rejected']"
                :percent="$metricPercent($overview['rejected'])" hint="RFC ditolak" tone="rose">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9.75 9.75l4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>
        </div>
    </section>

    {{-- Grafik --}}
    <div class="grid min-w-0 gap-5 xl:grid-cols-5">
        <x-dashboard.chart-card class="dashboard-rise dashboard-rise-2 xl:col-span-3" title="Tren {{ $periodLabel }}"
            description="Jumlah tiket masuk dan keputusan Change Manager (disetujui atau ditolak) {{ $overview['trend_granularity'] === 'week' ? 'per minggu' : 'setiap hari' }} pada zona waktu WIB.">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M3.75 19.5h16.5M6 16.5l4.5-4.5 3 2.25 4.5-6" />
                </svg>
            </x-slot:icon>

            <x-slot:actions>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500 shadow-sm">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500" aria-hidden="true"></span>
                        {{ $periodLabel }}
                    </span>

                    <select wire:model.live="period" aria-label="Pilih periode tren"
                        class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="7">7 Hari</option>
                        <option value="30">30 Hari</option>
                        <option value="90">90 Hari</option>
                        <option value="this_month">Bulan Ini</option>
                        <option value="last_month">Bulan Lalu</option>
                        <option value="custom">Kustom</option>
                    </select>

                    @if ($period === 'custom')
                        <div class="flex flex-wrap items-center gap-1">
                            <input type="date" wire:model.live="dateFrom" max="{{ now()->format('Y-m-d') }}"
                                aria-label="Tanggal mulai"
                                class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <span class="text-xs text-slate-400">s.d.</span>
                            <input type="date" wire:model.live="dateTo" max="{{ now()->format('Y-m-d') }}"
                                aria-label="Tanggal akhir"
                                class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                        </div>
                    @endif

                    @if (filled($periodError))
                        <p role="alert" class="basis-full text-right text-[11px] font-medium text-rose-700">
                            {{ $periodError }}
                        </p>
                    @endif
                </div>
            </x-slot:actions>

            <p class="mb-4 text-[11px] text-slate-500">
                Filter periode hanya berlaku untuk grafik tren. Kartu ringkasan menunjukkan posisi saat ini.
            </p>

            <x-dashboard.line-chart id="tren-cm-{{ $period }}" wire:key="tren-cm-{{ $period }}-{{ $dateFrom }}-{{ $dateTo }}"
                title="Tiket masuk dan keputusan Change Manager"
                description="Tren {{ strtolower($periodLabel) }} dengan agregasi {{ $overview['trend_granularity'] === 'week' ? 'per minggu' : 'harian' }}."
                primary-label="Tiket masuk" secondary-label="Keputusan CM"
                :labels="$overview['trend']['labels']" :table-labels="$overview['trend']['table_labels']"
                :primary="$overview['trend']['tickets']" :secondary="$overview['trend']['decisions']" />
        </x-dashboard.chart-card>

        <x-dashboard.chart-card class="dashboard-rise dashboard-rise-3 xl:col-span-2" title="Distribusi RFC per Tim Rilis"
            description="Jumlah RFC yang didisposisikan ke setiap anggota Tim Rilis. RFC terhapus tidak dihitung.">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M4.5 19.5v-7.5m5 7.5v-12m5 12v-5.25m5 5.25V6" />
                </svg>
            </x-slot:icon>

            <x-slot:actions>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500 shadow-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    Data aktif
                </span>
            </x-slot:actions>

            <x-dashboard.bar-chart id="distribusi-tim-rilis" title="Jumlah RFC per Tim Rilis"
                description="Jumlah dan porsi RFC yang ditugaskan ke setiap anggota Tim Rilis."
                :items="$overview['release_team_distribution']" :palette="true" noun="tim rilis"
                :show-terminal-groups="false" :columns="1"
                empty-description="Belum ada RFC yang didisposisikan ke Tim Rilis." />
        </x-dashboard.chart-card>
    </div>

    {{-- Antrean tindakan Change Manager --}}
    <x-dashboard.panel class="dashboard-rise dashboard-rise-3" title="Antrean Tindakan" id="antrean-tindakan"
        description="Lima RFC terlama yang membutuhkan tindakan Change Manager.">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M8.25 6.75h9M8.25 12h9M8.25 17.25h9M4.5 6.75h.008v.008H4.5V6.75Zm0 5.25h.008v.008H4.5V12Zm0 5.25h.008v.008H4.5v-.008Z" />
            </svg>
        </x-slot:icon>

        <x-slot:actions>
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-gradient-to-r from-blue-50 to-white px-3 py-1.5 text-xs font-bold text-blue-700 shadow-sm">
                <span class="h-1.5 w-1.5 rounded-full bg-blue-500" aria-hidden="true"></span>
                {{ number_format($overview['action_required'], 0, ',', '.') }} perlu tindakan
            </span>

            <a href="{{ route('change-requests.index') }}" wire:navigate
                class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-blue-600 transition hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200">
                Lihat semua
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                </svg>
            </a>
        </x-slot:actions>

        @if ($overview['queue'] === [])
            <div class="p-5">
                <x-dashboard.empty-state title="Belum ada tindakan yang menunggu"
                    description="RFC yang membutuhkan keputusan, implementasi, atau evaluasi akan tampil di sini." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="rfc-responsive-table w-full min-w-[980px] text-left text-xs text-slate-600">
                    <caption class="sr-only">Lima RFC terlama yang membutuhkan tindakan Change Manager</caption>
                    <thead
                        class="border-b border-slate-100 bg-slate-50/80 text-[10px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">No. RFC</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Judul</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Pemohon dan Unit</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Kategori</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Usia Tunggu</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/80">
                        @foreach ($overview['queue'] as $item)
                            @php
                                $requesterInitials = collect(
                                    preg_split('/\s+/', trim($item['requester'])) ?: [],
                                )
                                    ->filter()
                                    ->take(2)
                                    ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                                    ->implode('');
                            @endphp

                            <tr wire:key="antrean-cm-{{ $item['id'] }}"
                                class="group transition-colors duration-200 hover:bg-blue-50/40">
                                <td data-label="No. RFC" class="whitespace-nowrap px-5 py-4">
                                    <span
                                        class="inline-flex items-center rounded-lg border border-blue-100 bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-800">
                                        {{ $item['rfc_number'] }}
                                    </span>
                                </td>
                                <td data-label="Judul" class="max-w-[250px] px-5 py-4">
                                    <p class="line-clamp-2 font-semibold leading-snug text-slate-800">
                                        {{ $item['title'] }}
                                    </p>
                                </td>
                                <td data-label="Pemohon dan Unit" class="px-5 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-blue-500 to-sky-500 text-[9px] font-bold uppercase text-white shadow-sm"
                                            aria-hidden="true">{{ $requesterInitials ?: '?' }}</span>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-700">{{ $item['requester'] }}
                                            </p>
                                            <p class="mt-0.5 truncate text-[11px] text-slate-400">{{ $item['unit'] }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Kategori" class="whitespace-nowrap px-5 py-4">
                                    <x-category-badge :kategori="$item['category']" size="sm" />
                                </td>
                                <td data-label="Usia Tunggu" class="whitespace-nowrap px-5 py-4">
                                    <x-dashboard.wait-age :days="$item['wait_days']" :since="$item['waiting_since']" />
                                </td>
                                <td data-label="Status" class="whitespace-nowrap px-5 py-4">
                                    <x-status-badge :status="$item['status']" />
                                </td>
                                <td data-label="Aksi" class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('change-requests.' . $item['action_route'], $item['id']) }}"
                                        wire:navigate
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-blue-600 to-sky-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-blue-500/20 transition duration-200 hover:-translate-y-0.5 hover:from-blue-700 hover:to-sky-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200">
                                        {{ $item['action_label'] }}
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="m9 5 7 7-7 7" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-dashboard.panel>

    {{-- Aktivitas terkini --}}
    <x-dashboard.panel class="dashboard-rise dashboard-rise-4" title="Aktivitas Terkini" id="aktivitas-terkini-cm"
        description="Lima kejadian terakhir pada RFC yang ditangani Change Manager.">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M12 8.25v3.75l2.25 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </x-slot:icon>

        <x-slot:actions>
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500 shadow-sm">
                <span class="h-1.5 w-1.5 rounded-full bg-slate-400" aria-hidden="true"></span>
                5 terakhir
            </span>
        </x-slot:actions>

        @if ($overview['activities'] === [])
            <div class="p-5">
                <x-dashboard.empty-state title="Belum ada aktivitas terkini"
                    description="Kejadian pada RFC akan tampil di sini." />
            </div>
        @else
            <ol class="divide-y divide-slate-100/80">
                @foreach ($overview['activities'] as $activity)
                    <x-dashboard.activity-item wire:key="aktivitas-cm-{{ $loop->index }}"
                        :rfc-number="$activity['rfc_number']" :label="$activity['label']"
                        :actor-name="$activity['actor_name']" :occurred-at="$activity['occurred_at']"
                        :is-last="$loop->last" />
                @endforeach
            </ol>
        @endif
    </x-dashboard.panel>
</div>
