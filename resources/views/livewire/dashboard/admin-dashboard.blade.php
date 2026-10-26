<div class="space-y-6">

    {{-- Banner sambutan --}}
    <x-dashboard.hero-banner title="Dashboard Admin"
        description="Pantau antrean pengajuan RFC, pembuatan tiket change, dan kualitas data layanan perubahan TI Diskominfostandi Kota Bekasi."
        :user-name="$user->name" :date-time="$now->translatedFormat('l, j F Y') . ' pukul ' . $now->format('H:i')"
        role-label="Admin / Frontdesk" />

    {{-- Lima kartu metrik --}}
    <section aria-labelledby="metrik-admin">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 id="metrik-admin" class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                    Ringkasan Layanan
                </h2>
                <p class="mt-1 text-xs text-slate-500">Antrean saat ini dan aktivitas 30 hari terakhir.</p>
            </div>

            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-slate-500 shadow-sm">
                <span class="h-1.5 w-1.5 rounded-full bg-blue-500" aria-hidden="true"></span>
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
            class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 sm:flex-row sm:items-center">
            <span
                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-amber-600 ring-1 ring-amber-200">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M12 9v3.75m0 3h.008M10.34 3.94 2.7 17.25A2.25 2.25 0 0 0 4.65 20.5h14.7a2.25 2.25 0 0 0 1.95-3.25L13.66 3.94a2.25 2.25 0 0 0-3.32 0Z" />
                </svg>
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-amber-900">Perhatian kualitas data</p>
                <p class="mt-0.5 text-xs leading-relaxed text-amber-800">{{ $overview['quality']['note'] }}</p>
            </div>

            <span
                class="inline-flex w-fit shrink-0 items-center rounded-full bg-white px-3 py-1 text-[11px] font-bold text-amber-700 ring-1 ring-amber-200">
                {{ number_format($overview['quality']['missing_ticket_number'], 0, ',', '.') }} RFC
            </span>
        </div>
    @endif

    {{-- Rincian atribusi penolakan --}}
    @if ($overview['rejected']['current'] > 0)
        <x-dashboard.panel title="Rincian Penolakan" id="rincian-penolakan"
            description="Atribusi berdasarkan status asal pada jejak aktivitas RFC dalam 30 hari terakhir.">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M12 9v3.75m0 3h.008M10.34 3.94 2.7 17.25A2.25 2.25 0 0 0 4.65 20.5h14.7a2.25 2.25 0 0 0 1.95-3.25L13.66 3.94a2.25 2.25 0 0 0-3.32 0Z" />
                </svg>
            </x-slot:icon>

            <x-slot:actions>
                <span
                    class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-100">
                    {{ number_format($overview['rejected']['current'], 0, ',', '.') }} RFC ditolak
                </span>
            </x-slot:actions>

            <dl class="grid gap-3 p-5 sm:grid-cols-3">
                @foreach ($overview['rejected']['breakdown'] as $item)
                    @php
                        $share = ($item['count'] / $overview['rejected']['current']) * 100;
                        $tone = match ($item['key']) {
                            'admin' => ['dot' => 'bg-blue-500', 'bar' => 'bg-blue-500'],
                            'change_manager' => ['dot' => 'bg-emerald-500', 'bar' => 'bg-emerald-500'],
                            default => ['dot' => 'bg-slate-400', 'bar' => 'bg-slate-400'],
                        };
                    @endphp

                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                        <dt class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <span class="h-2 w-2 rounded-full {{ $tone['dot'] }}" aria-hidden="true"></span>
                            {{ $item['label'] }}
                        </dt>
                        <dd class="mt-2 flex items-end justify-between gap-2">
                            <span class="text-2xl font-bold leading-none tabular-nums text-slate-900">
                                {{ number_format($item['count'], 0, ',', '.') }}
                            </span>
                            <span class="text-[11px] font-semibold tabular-nums text-slate-500">
                                {{ number_format($share, 1, ',', '.') }}%
                            </span>
                        </dd>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white ring-1 ring-slate-200/70"
                            aria-hidden="true">
                            <div class="h-full rounded-full {{ $tone['bar'] }}"
                                style="width: {{ number_format($share, 2, '.', '') }}%"></div>
                        </div>
                    </div>
                @endforeach
            </dl>
        </x-dashboard.panel>
    @endif

    {{-- Grafik --}}
    <div class="grid min-w-0 gap-6 xl:grid-cols-2">
        <x-dashboard.chart-card title="Tren 30 Hari"
            description="Jumlah RFC yang dibuat (selain draft) dan tiket change yang dibuat setiap hari pada zona waktu WIB.">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M3.75 19.5h16.5M6 16.5l4.5-4.5 3 2.25 4.5-6" />
                </svg>
            </x-slot:icon>

            <x-slot:actions>
                <span
                    class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    30 hari
                </span>
            </x-slot:actions>

            <x-dashboard.line-chart id="tren-30-hari" title="RFC dibuat dan tiket dibuat"
                description="Tren harian selama 30 hari terakhir." primary-label="RFC dibuat (selain draft)"
                :labels="$overview['trend']['labels']" :table-labels="$overview['trend']['table_labels']"
                :primary="$overview['trend']['created']" :secondary="$overview['trend']['tickets']" />
        </x-dashboard.chart-card>

        <x-dashboard.chart-card title="Distribusi Status RFC"
            description="Sebaran seluruh status RFC yang tersimpan saat ini, tanpa RFC terhapus. Enam status terbanyak ditampilkan terpisah; sisanya digabung sebagai Lainnya.">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M4.5 19.5v-7.5m5 7.5v-12m5 12v-5.25m5 5.25V6" />
                </svg>
            </x-slot:icon>

            <x-slot:actions>
                <span
                    class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    Data aktif
                </span>
            </x-slot:actions>

            <x-dashboard.bar-chart id="distribusi-status" title="Jumlah RFC per status"
                description="Klik “Lihat data tabel” untuk melihat seluruh status tanpa penggabungan."
                :items="$overview['distribution']" :group-remaining="true" :max-visible="6" />
        </x-dashboard.chart-card>
    </div>

    {{-- Antrean RFC masuk --}}
    <x-dashboard.panel title="Antrean RFC Masuk" id="antrean-rfc-masuk"
        description="Lima RFC terlama yang menunggu pembuatan tiket change.">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M3.75 6.75h16.5v10.5H3.75V6.75Zm0 5.25h4.5l1.5 2.25h4.5l1.5-2.25h4.5" />
            </svg>
        </x-slot:icon>

        <x-slot:actions>
            <span
                class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-100">
                {{ number_format($overview['waiting_ticket'], 0, ',', '.') }} menunggu
            </span>

            <a href="{{ route('change-requests.index', ['queue' => 'incoming']) }}" wire:navigate
                class="inline-flex items-center gap-1.5 rounded-lg px-1.5 py-1 text-xs font-semibold text-blue-600 transition hover:text-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200">
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
                        class="border-b border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wide text-slate-500">
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
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($overview['queue'] as $item)
                            @php
                                $categoryTone = match ($item['category']) {
                                    'Emergency' => 'bg-rose-50 text-rose-700 ring-rose-100',
                                    'Normal' => 'bg-blue-50 text-blue-700 ring-blue-100',
                                    'Standar' => 'bg-slate-100 text-slate-600 ring-slate-200',
                                    default => 'bg-slate-100 text-slate-500 ring-slate-200',
                                };
                            @endphp

                            <tr wire:key="antrean-rfc-{{ $item['id'] }}"
                                class="transition hover:bg-blue-50/30">
                                <td data-label="No. RFC"
                                    class="whitespace-nowrap px-5 py-4 font-bold text-blue-900">
                                    {{ $item['rfc_number'] }}
                                </td>
                                <td data-label="Judul" class="max-w-[250px] px-5 py-4">
                                    <p class="line-clamp-2 font-medium leading-snug text-slate-800">
                                        {{ $item['title'] }}
                                    </p>
                                </td>
                                <td data-label="Pemohon dan Unit" class="px-5 py-4">
                                    <p class="font-medium text-slate-700">{{ $item['requester'] }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $item['unit'] }}</p>
                                </td>
                                <td data-label="Kategori" class="whitespace-nowrap px-5 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 {{ $categoryTone }}">
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
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200">
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
    <x-dashboard.panel title="Aktivitas Terkini" id="aktivitas-terkini"
        description="Lima kejadian terakhir pada RFC yang masih tersimpan.">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M12 8.25v3.75l2.25 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </x-slot:icon>

        <x-slot:actions>
            <span
                class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                5 terakhir
            </span>
        </x-slot:actions>

        @if ($overview['activities'] === [])
            <div class="p-5">
                <x-dashboard.empty-state title="Belum ada aktivitas terkini"
                    description="Kejadian pada RFC akan tampil di sini." />
            </div>
        @else
            <ol class="divide-y divide-slate-100">
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
