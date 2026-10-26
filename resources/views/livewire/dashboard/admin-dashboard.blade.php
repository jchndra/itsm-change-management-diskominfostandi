<div class="space-y-5 sm:space-y-6">

    {{-- Banner sambutan --}}
    <x-dashboard.hero-banner class="dashboard-rise" title="Dashboard Admin"
        description="Pantau antrean pengajuan RFC, pembuatan tiket change, dan kualitas data layanan perubahan TI Diskominfostandi Kota Bekasi."
        :user-name="$user->name" :date-time="$now->translatedFormat('l, j F Y') . ' pukul ' . $now->format('H:i')"
        role-label="Admin / Frontdesk" />

    {{-- Lima kartu metrik --}}
    <section aria-labelledby="metrik-admin" class="dashboard-rise dashboard-rise-1">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 h-10 w-1 rounded-full bg-gradient-to-b from-blue-500 via-sky-400 to-amber-300"
                    aria-hidden="true"></span>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-blue-600">Ikhtisar Dashboard</p>
                    <h2 id="metrik-admin" class="mt-0.5 text-lg font-bold tracking-tight text-slate-900">
                        Ringkasan Layanan
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">Antrean saat ini dan aktivitas 30 hari terakhir.</p>
                </div>
            </div>

            <span
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-semibold text-slate-500 shadow-sm">
                <span class="relative flex h-2 w-2" aria-hidden="true">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
                Diperbarui {{ $now->translatedFormat('d M Y, H:i') }} WIB
            </span>
        </div>

        <div class="grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <x-dashboard.stat-card label="Menunggu Tiket" :value="$overview['waiting_ticket']"
                hint="Diajukan atau dikembalikan ke Admin" tone="blue">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M3.75 6.75h16.5v10.5H3.75V6.75Zm0 5.25h4.5l1.5 2.25h4.5l1.5-2.25h4.5" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Dikembalikan CM" :value="$overview['returned_to_admin']"
                hint="Menunggu tindak lanjut Admin" tone="indigo">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9 15 4.5 10.5 9 6m-4.5 4.5h9a5.25 5.25 0 1 1 0 10.5h-3" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Perlu Revisi" :value="$overview['needs_revision']"
                hint="Menunggu perbaikan dari Pemohon" tone="amber">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M12 9v3.75m0 3h.008M10.34 3.94 2.7 17.25A2.25 2.25 0 0 0 4.65 20.5h14.7a2.25 2.25 0 0 0 1.95-3.25L13.66 3.94a2.25 2.25 0 0 0-3.32 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Tiket Dibuat" :value="$overview['tickets_created']['current']"
                hint="Tiket change dibuat dalam 30 hari" tone="emerald" :change="$overview['tickets_created']['change_percent']"
                :show-comparison="true">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M3.75 7.5h16.5v9H3.75v-9Zm0 4.5h4.5l1.5 2.25h4.5l1.5-2.25h4.5" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Ditolak" :value="$overview['rejected']['current']"
                hint="RFC ditolak dalam 30 hari" tone="rose" :change="$overview['rejected']['change_percent']"
                :show-comparison="true" comparison-polarity="inverse">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>
        </div>
    </section>

    {{-- Kualitas data RFC lama --}}
    @if ($overview['quality']['note'])
        <div role="note"
            class="dashboard-rise dashboard-rise-2 relative flex flex-col gap-3 overflow-hidden rounded-2xl border border-amber-200/80 bg-gradient-to-r from-amber-50 via-amber-50/80 to-white p-4 shadow-sm sm:flex-row sm:items-center">
            <span class="pointer-events-none absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-amber-400 to-orange-400"
                aria-hidden="true"></span>
            <span
                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-400 text-white shadow-md shadow-amber-500/20 ring-1 ring-white/30">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M12 9v3.75m0 3h.008M10.34 3.94 2.7 17.25A2.25 2.25 0 0 0 4.65 20.5h14.7a2.25 2.25 0 0 0 1.95-3.25L13.66 3.94a2.25 2.25 0 0 0-3.32 0Z" />
                </svg>
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-amber-950">Perhatian kualitas data</p>
                <p class="mt-0.5 text-xs leading-relaxed text-amber-800">{{ $overview['quality']['note'] }}</p>
            </div>

            <span
                class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border border-amber-200 bg-white px-3 py-1.5 text-[11px] font-bold text-amber-700 shadow-sm">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                {{ number_format($overview['quality']['missing_ticket_number'], 0, ',', '.') }} RFC
            </span>
        </div>
    @endif

    {{-- Rincian atribusi penolakan --}}
    @if ($overview['rejected']['current'] > 0)
        <x-dashboard.panel class="dashboard-rise dashboard-rise-2" title="Rincian Penolakan" id="rincian-penolakan"
            description="Atribusi berdasarkan status asal pada jejak aktivitas RFC dalam 30 hari terakhir.">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M12 9v3.75m0 3h.008M10.34 3.94 2.7 17.25A2.25 2.25 0 0 0 4.65 20.5h14.7a2.25 2.25 0 0 0 1.95-3.25L13.66 3.94a2.25 2.25 0 0 0-3.32 0Z" />
                </svg>
            </x-slot:icon>

            <x-slot:actions>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 shadow-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500" aria-hidden="true"></span>
                    {{ number_format($overview['rejected']['current'], 0, ',', '.') }} RFC ditolak
                </span>
            </x-slot:actions>

            <div class="p-5">
                <div class="mb-5">
                    <div class="mb-2 flex items-center justify-between text-[10px] font-bold uppercase tracking-[0.12em]">
                        <span class="text-slate-500">Komposisi penolakan</span>
                        <span class="text-slate-400">30 hari</span>
                    </div>
                    <div class="flex h-3 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200/70"
                        aria-hidden="true">
                        @foreach ($overview['rejected']['breakdown'] as $item)
                            @php
                                $share = ($item['count'] / $overview['rejected']['current']) * 100;
                                $bar = match ($item['key']) {
                                    'admin' => 'from-blue-500 to-sky-400',
                                    'change_manager' => 'from-emerald-500 to-teal-400',
                                    default => 'from-slate-400 to-slate-300',
                                };
                            @endphp
                            <div class="h-full bg-gradient-to-r first:rounded-l-full last:rounded-r-full {{ $bar }}"
                                style="width: {{ number_format($share, 2, '.', '') }}%"></div>
                        @endforeach
                    </div>
                </div>

                <dl class="grid gap-3 sm:grid-cols-3">
                    @foreach ($overview['rejected']['breakdown'] as $item)
                        @php
                            $share = ($item['count'] / $overview['rejected']['current']) * 100;
                            $tone = match ($item['key']) {
                                'admin' => [
                                    'dot' => 'bg-blue-500',
                                    'bar' => 'from-blue-500 to-sky-400',
                                    'soft' => 'from-blue-50/80',
                                ],
                                'change_manager' => [
                                    'dot' => 'bg-emerald-500',
                                    'bar' => 'from-emerald-500 to-teal-400',
                                    'soft' => 'from-emerald-50/80',
                                ],
                                default => [
                                    'dot' => 'bg-slate-400',
                                    'bar' => 'from-slate-400 to-slate-300',
                                    'soft' => 'from-slate-50/80',
                                ],
                            };
                        @endphp

                        <div
                            class="rounded-2xl border border-slate-200/70 bg-gradient-to-br {{ $tone['soft'] }} to-white p-4 shadow-sm transition duration-200 hover:border-slate-300 hover:shadow-md">
                            <dt class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">
                                <span class="h-2 w-2 rounded-full {{ $tone['dot'] }}" aria-hidden="true"></span>
                                {{ $item['label'] }}
                            </dt>
                            <dd class="mt-3 flex items-end justify-between gap-2">
                                <span class="text-2xl font-bold leading-none tabular-nums tracking-tight text-slate-900">
                                    {{ number_format($item['count'], 0, ',', '.') }}
                                </span>
                                <span
                                    class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-bold tabular-nums text-slate-500 shadow-sm">
                                    {{ number_format($share, 1, ',', '.') }}%
                                </span>
                            </dd>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white ring-1 ring-slate-200/70"
                                aria-hidden="true">
                                <div class="h-full rounded-full bg-gradient-to-r {{ $tone['bar'] }}"
                                    style="width: {{ number_format($share, 2, '.', '') }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </div>
        </x-dashboard.panel>
    @endif

    {{-- Grafik --}}
    <div class="grid min-w-0 gap-5 xl:grid-cols-5">
        <x-dashboard.chart-card class="dashboard-rise dashboard-rise-2 xl:col-span-3" title="Tren 30 Hari"
            description="Jumlah RFC yang dibuat (selain draft) dan tiket change yang dibuat setiap hari pada zona waktu WIB.">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M3.75 19.5h16.5M6 16.5l4.5-4.5 3 2.25 4.5-6" />
                </svg>
            </x-slot:icon>

            <x-slot:actions>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500 shadow-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-blue-500" aria-hidden="true"></span>
                    30 hari
                </span>
            </x-slot:actions>

            <x-dashboard.line-chart id="tren-30-hari" title="RFC dibuat dan tiket dibuat"
                description="Tren harian selama 30 hari terakhir." primary-label="RFC dibuat (selain draft)"
                :labels="$overview['trend']['labels']" :table-labels="$overview['trend']['table_labels']"
                :primary="$overview['trend']['created']" :secondary="$overview['trend']['tickets']" />
        </x-dashboard.chart-card>

        <x-dashboard.chart-card class="dashboard-rise dashboard-rise-3 xl:col-span-2" title="Distribusi Status RFC"
            description="Sebaran seluruh status RFC yang tersimpan saat ini, tanpa RFC terhapus. Enam status terbanyak ditampilkan terpisah; sisanya digabung sebagai Lainnya.">
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

            <x-dashboard.bar-chart id="distribusi-status" title="Jumlah RFC per status"
                description="Klik “Lihat data tabel” untuk melihat seluruh status tanpa penggabungan."
                :items="$overview['distribution']" :group-remaining="true" :max-visible="6" />
        </x-dashboard.chart-card>
    </div>

    {{-- Antrean RFC masuk --}}
    <x-dashboard.panel class="dashboard-rise dashboard-rise-3" title="Antrean RFC Masuk" id="antrean-rfc-masuk"
        description="Lima RFC terlama yang menunggu pembuatan tiket change.">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M3.75 6.75h16.5v10.5H3.75V6.75Zm0 5.25h4.5l1.5 2.25h4.5l1.5-2.25h4.5" />
            </svg>
        </x-slot:icon>

        <x-slot:actions>
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-gradient-to-r from-blue-50 to-white px-3 py-1.5 text-xs font-bold text-blue-700 shadow-sm">
                <span class="h-1.5 w-1.5 rounded-full bg-blue-500" aria-hidden="true"></span>
                {{ number_format($overview['waiting_ticket'], 0, ',', '.') }} menunggu
            </span>

            <a href="{{ route('change-requests.index', ['queue' => 'incoming']) }}" wire:navigate
                class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-blue-600 transition hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200">
                Lihat semua
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                </svg>
            </a>
        </x-slot:actions>

        @if ($overview['queue'] === [])
            <div class="p-5">
                <x-dashboard.empty-state title="Belum ada antrean RFC masuk"
                    description="RFC yang menunggu pembuatan tiket change akan tampil di sini." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="rfc-responsive-table w-full min-w-[980px] text-left text-xs text-slate-600">
                    <caption class="sr-only">Lima RFC terlama yang menunggu pembuatan tiket change</caption>
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
                                $categoryTone = match ($item['category']) {
                                    'Emergency' => 'bg-rose-50 text-rose-700 ring-rose-100',
                                    'Normal' => 'bg-blue-50 text-blue-700 ring-blue-100',
                                    'Standar' => 'bg-slate-100 text-slate-600 ring-slate-200',
                                    default => 'bg-slate-100 text-slate-500 ring-slate-200',
                                };
                                $requesterInitials = collect(
                                    preg_split('/\s+/', trim($item['requester'])) ?: [],
                                )
                                    ->filter()
                                    ->take(2)
                                    ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                                    ->implode('');
                            @endphp

                            <tr wire:key="antrean-rfc-{{ $item['id'] }}"
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
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold ring-1 {{ $categoryTone }}">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"
                                            aria-hidden="true"></span>
                                        {{ $item['category'] }}
                                    </span>
                                </td>
                                <td data-label="Usia Tunggu" class="whitespace-nowrap px-5 py-4">
                                    <x-dashboard.wait-age :days="$item['wait_days']" :since="$item['waiting_since']" />
                                </td>
                                <td data-label="Status" class="whitespace-nowrap px-5 py-4">
                                    <x-status-badge :status="$item['status']" />
                                </td>
                                <td data-label="Aksi" class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('change-requests.review', $item['id']) }}" wire:navigate
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-blue-600 to-sky-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-blue-500/20 transition duration-200 hover:-translate-y-0.5 hover:from-blue-700 hover:to-sky-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3.75 7.5h16.5v9H3.75v-9Zm0 4.5h4.5l1.5 2.25h4.5l1.5-2.25h4.5" />
                                        </svg>
                                        Buat Tiket
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
    <x-dashboard.panel class="dashboard-rise dashboard-rise-4" title="Aktivitas Terkini" id="aktivitas-terkini"
        description="Lima kejadian terakhir pada RFC yang masih tersimpan.">
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
                    <x-dashboard.activity-item wire:key="aktivitas-{{ $loop->index }}"
                        :rfc-number="$activity['rfc_number']" :label="$activity['label']"
                        :actor-name="$activity['actor_name']" :occurred-at="$activity['occurred_at']"
                        :is-last="$loop->last" />
                @endforeach
            </ol>
        @endif
    </x-dashboard.panel>
</div>
