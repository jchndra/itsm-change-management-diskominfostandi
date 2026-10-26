<div class="space-y-6">

    {{-- Banner sambutan --}}
    <x-dashboard.hero-banner title="Dashboard Change Manager"
        description="Kelola penilaian risiko, keputusan, implementasi, hingga evaluasi RFC agar perubahan layanan TI berjalan terkendali."
        :user-name="$user->name" :date-time="$now->translatedFormat('l, j F Y') . ' pukul ' . $now->format('H:i')"
        role-label="Change Manager" />

    {{-- Lima kartu metrik --}}
    <section aria-labelledby="metrik-change-manager">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 id="metrik-change-manager"
                    class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                    Ringkasan Layanan
                </h2>
                <p class="mt-1 text-xs text-slate-500">Posisi RFC yang menjadi tanggung jawab Change Manager.</p>
            </div>

            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-slate-500 shadow-sm">
                <span class="h-1.5 w-1.5 rounded-full bg-blue-500" aria-hidden="true"></span>
                Diperbarui {{ $now->translatedFormat('d M Y, H:i') }} WIB
            </span>
        </div>

        <div class="grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <x-dashboard.stat-card label="Menunggu Review" :value="$overview['pending_review']"
                hint="Perlu penilaian risiko dan keputusan" tone="blue">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9 5.25h6M9 5.25V3.75h6v1.5M9 5.25H6.75A1.5 1.5 0 0 0 5.25 6.75v13.5a1.5 1.5 0 0 0 1.5 1.5h10.5a1.5 1.5 0 0 0 1.5-1.5V6.75a1.5 1.5 0 0 0-1.5-1.5H15M8.25 12l2.25 2.25 5.25-5.25" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Disetujui" :value="$overview['approved']"
                hint="Siap memulai implementasi" tone="emerald">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Sedang Dikerjakan" :value="$overview['in_progress']"
                hint="Implementasi sedang berjalan" tone="indigo">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M11.42 6.42a4.5 4.5 0 0 0 6.16 6.16l3.17 3.17a2.25 2.25 0 0 1-3.18 3.18l-3.17-3.17a4.5 4.5 0 0 1-6.16-6.16l2.12 2.12 3.18-3.18-2.12-2.12Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Menunggu Evaluasi" :value="$overview['waiting_evaluation']"
                hint="Perlu evaluasi dan penutupan" tone="amber">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M12 8.25v3.75l2.25 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Ditutup" :value="$overview['closed']"
                hint="RFC telah ditutup" tone="slate">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0 1 19.5 12.75v6A2.25 2.25 0 0 1 17.25 21H6.75a2.25 2.25 0 0 1-2.25-2.25v-6a2.25 2.25 0 0 1 2.25-2.25Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>
        </div>
    </section>

    {{-- Grafik --}}
    <div class="grid min-w-0 gap-6 xl:grid-cols-2">
        <x-dashboard.chart-card title="Tren 30 Hari"
            description="Jumlah tiket masuk dan keputusan Change Manager (disetujui atau ditolak) setiap hari pada zona waktu WIB.">
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

            <x-dashboard.line-chart id="tren-cm-30-hari" title="Tiket masuk dan keputusan Change Manager"
                description="Tren harian selama 30 hari terakhir." primary-label="Tiket masuk"
                secondary-label="Keputusan CM" :labels="$overview['trend']['labels']"
                :table-labels="$overview['trend']['table_labels']" :primary="$overview['trend']['tickets']"
                :secondary="$overview['trend']['decisions']" />
        </x-dashboard.chart-card>

        <x-dashboard.chart-card title="Distribusi Status RFC"
            description="Sebaran status RFC yang tersimpan saat ini, dibedakan antara status aktif dan terminal. RFC terhapus tidak dihitung.">
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

            <x-dashboard.bar-chart id="distribusi-status-cm" title="Jumlah RFC per status"
                description="Jumlah dan porsi setiap status terhadap total RFC pada dashboard."
                :items="$overview['distribution']" :show-terminal-groups="true" :columns="2" />
        </x-dashboard.chart-card>
    </div>

    {{-- Antrean tindakan Change Manager --}}
    <x-dashboard.panel title="Antrean Tindakan" id="antrean-tindakan"
        description="Lima RFC terlama yang membutuhkan tindakan Change Manager.">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M8.25 6.75h9M8.25 12h9M8.25 17.25h9M4.5 6.75h.008v.008H4.5V6.75Zm0 5.25h.008v.008H4.5V12Zm0 5.25h.008v.008H4.5v-.008Z" />
            </svg>
        </x-slot:icon>

        <x-slot:actions>
            <span
                class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-100">
                {{ number_format($overview['action_required'], 0, ',', '.') }} perlu tindakan
            </span>

            <a href="{{ route('change-requests.index') }}" wire:navigate
                class="inline-flex items-center gap-1.5 rounded-lg px-1.5 py-1 text-xs font-semibold text-blue-600 transition hover:text-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200">
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

                            <tr wire:key="antrean-cm-{{ $item['id'] }}"
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
                                    <a href="{{ route('change-requests.' . $item['action_route'], $item['id']) }}"
                                        wire:navigate
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200">
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
    <x-dashboard.panel title="Aktivitas Terkini" id="aktivitas-terkini-cm"
        description="Lima kejadian terakhir pada RFC yang ditangani Change Manager.">
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
                    <x-dashboard.activity-item wire:key="aktivitas-cm-{{ $loop->index }}"
                        :rfc-number="$activity['rfc_number']" :label="$activity['label']"
                        :actor-name="$activity['actor_name']" :occurred-at="$activity['occurred_at']"
                        :is-last="$loop->last" />
                @endforeach
            </ol>
        @endif
    </x-dashboard.panel>
</div>
