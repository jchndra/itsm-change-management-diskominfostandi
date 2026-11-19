<div class="space-y-5 sm:space-y-6">
    @php
        $roleLabels = [
            'pemohon' => 'Pemohon',
            'admin' => 'Admin / Frontdesk',
            'release_team' => 'Tim Rilis',
            'change_manager' => 'Change Manager',
        ];

        $heroTitles = [
            'pemohon' => 'RFC Saya',
            'admin' => 'Daftar Permintaan Perubahan',
            'release_team' => 'Tugas Saya',
            'change_manager' => 'Daftar Permintaan Perubahan',
        ];

        $heroQuotes = [
            'pemohon' => 'Jadikan setiap perubahan sebagai langkah menuju layanan publik yang lebih baik.',
            'admin' => 'Kelola penerimaan dan verifikasi administrasi dokumen perubahan secara teliti dan cepat.',
            'release_team' => 'Kerjakan RFC yang ditugaskan dengan bukti pelaksanaan yang lengkap dan dapat diaudit.',
            'change_manager' => 'Kelola perubahan layanan TI secara aman, terukur, transparan, dan terdokumentasi.',
        ];

        $listTitles = [
            'pemohon' => 'Daftar RFC Saya',
            'admin' => 'Daftar Permintaan Perubahan (RFC)',
            'release_team' => 'Daftar Tugas',
            'change_manager' => 'Daftar Permintaan Perubahan (RFC)',
        ];

        $listDescriptions = [
            'pemohon' => 'Pantau status setiap usulan perubahan yang Anda ajukan.',
            'admin' => 'Pantau dan kelola seluruh usulan perubahan sistem informasi.',
            'release_team' => 'RFC yang ditugaskan kepada Anda beserta status pelaksanaannya.',
            'change_manager' => 'Pantau dan kelola seluruh usulan perubahan sistem informasi.',
        ];

        $formatFilterDate = static function (?string $value): ?string {
            if (blank($value)) {
                return null;
            }

            try {
                return \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $value)
                    ->locale('id')
                    ->translatedFormat('d M Y');
            } catch (\Throwable) {
                return $value;
            }
        };

        $filterChips = collect([
            filled($statusFilter)
                ? ['property' => 'statusFilter', 'label' => 'Status', 'value' => $statuses->get($statusFilter, $statusFilter)]
                : null,
            filled($categoryFilter)
                ? ['property' => 'categoryFilter', 'label' => 'Jenis', 'value' => $changeTypes[$categoryFilter] ?? $categoryFilter]
                : null,
            filled($applicationFilter)
                ? ['property' => 'applicationFilter', 'label' => 'Aplikasi', 'value' => $applications->firstWhere('id', (int) $applicationFilter)?->name ?? 'Aplikasi terpilih']
                : null,
            filled($opdFilter)
                ? ['property' => 'opdFilter', 'label' => 'Unit', 'value' => $opds->firstWhere('id', (int) $opdFilter)?->name ?? 'Unit terpilih']
                : null,
            filled($dateFrom)
                ? ['property' => 'dateFrom', 'label' => 'Dari', 'value' => $formatFilterDate($dateFrom)]
                : null,
            filled($dateTo)
                ? ['property' => 'dateTo', 'label' => 'Sampai', 'value' => $formatFilterDate($dateTo)]
                : null,
        ])->filter()->values();

        $activeFilterCount = $filterChips->count();
        $totalRfc = (int) ($stats['total'] ?? $rfcs->total());
        $statusPercent = fn (int $value): float => $totalRfc > 0 ? ($value / $totalRfc) * 100 : 0.0;
    @endphp

    {{-- Banner halaman --}}
    <x-dashboard.hero-banner class="dashboard-rise" :title="$heroTitles[$roleView] ?? 'Daftar Permintaan Perubahan'"
        :user-name="auth()->user()->name" :role-label="$roleLabels[$roleView] ?? 'Pengguna'"
        :description="$heroQuotes[$roleView] ?? $heroQuotes['change_manager']" :show-meta="false">
        @if ($roleView === 'pemohon')
            <x-slot:actions>
                <a href="{{ route('change-requests.create') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#08294a] shadow-lg shadow-black/10 transition hover:-translate-y-0.5 hover:bg-blue-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-300/40">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Ajukan RFC Baru
                </a>
            </x-slot:actions>
        @endif
    </x-dashboard.hero-banner>

    {{-- Notifikasi status --}}
    @if ($statusMessage)
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-800 shadow-sm"
            role="status">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor"
                viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <p class="flex-1 font-medium">{{ $statusMessage }}</p>
            <button type="button" wire:click="$set('statusMessage', '')" class="text-emerald-500 hover:text-emerald-700"
                aria-label="Tutup notifikasi">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    {{-- Kartu statistik --}}
    @if ($roleView === 'pemohon')
        <div class="dashboard-rise dashboard-rise-1 grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-dashboard.stat-card label="RFC Saya" :value="$stats['my_rfc']" hint="Total usulan yang Anda ajukan"
                tone="blue">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M14 3v4a1 1 0 0 0 1 1h4M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9 12h6M9 16h4" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card :label="\App\Enums\RfcStatus::groupLabel('in_process')" :value="$stats['in_process']"
                hint="Sedang diproses oleh tim" tone="indigo">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M12 8.25v3.75l2.25 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card :label="\App\Enums\RfcStatus::groupLabel('needs_revision')"
                :value="$stats['needs_revision']" hint="Menunggu perbaikan dari Anda" tone="amber">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M11.42 6.42a4.5 4.5 0 0 0 6.16 6.16l3.17 3.17a2.25 2.25 0 0 1-3.18 3.18l-3.17-3.17a4.5 4.5 0 0 1-6.16-6.16l2.12 2.12 3.18-3.18-2.12-2.12Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card :label="\App\Enums\RfcStatus::groupLabel('approved')" :value="$stats['approved']"
                hint="Disetujui untuk dilaksanakan" tone="emerald">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card :label="\App\Enums\RfcStatus::groupLabel('rejected')" :value="$stats['rejected']"
                hint="Ditolak atau tidak terlaksana" tone="rose">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9.75 9.75l4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>
        </div>
    @elseif ($roleView === 'admin')
        <div class="dashboard-rise dashboard-rise-1 grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('change-requests.index', ['queue' => 'incoming']) }}" class="block h-full">
                <x-dashboard.stat-card label="RFC Masuk" :value="$stats['incoming']"
                    hint="Buka antrean buat tiket change" tone="blue" class="h-full">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M3.75 7.5h16.5v9H3.75v-9Zm0 4.5h4.5l1.5 2.25h4.5l1.5-2.25h4.5" />
                        </svg>
                    </x-slot:icon>
                </x-dashboard.stat-card>
            </a>

            <x-dashboard.stat-card label="Perlu Revisi" :value="$stats['needs_revision']"
                hint="Dikembalikan ke pemohon" tone="amber">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M11.42 6.42a4.5 4.5 0 0 0 6.16 6.16l3.17 3.17a2.25 2.25 0 0 1-3.18 3.18l-3.17-3.17a4.5 4.5 0 0 1-6.16-6.16l2.12 2.12 3.18-3.18-2.12-2.12Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Diteruskan" :value="$stats['forwarded']"
                hint="Sudah dibuatkan tiket change" tone="emerald">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="m9 5 7 7-7 7" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card :label="\App\Enums\RfcStatus::groupLabel('rejected')" :value="$stats['rejected']"
                hint="Ditolak atau tidak terlaksana" tone="rose">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9.75 9.75l4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>
        </div>
    @elseif ($roleView === 'release_team')
        <div class="dashboard-rise dashboard-rise-1 grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-dashboard.stat-card label="RFC Ditugaskan" :value="$stats['assigned']"
                hint="Seluruh tugas yang tercatat" tone="blue">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9 5.25h6M9 5.25V3.75h6v1.5M9 5.25H6.75A1.5 1.5 0 0 0 5.25 6.75v13.5a1.5 1.5 0 0 0 1.5 1.5h10.5a1.5 1.5 0 0 0 1.5-1.5V6.75a1.5 1.5 0 0 0-1.5-1.5H15M8.25 12l2.25 2.25 5.25-5.25" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Sedang Dikerjakan" :value="$stats['in_progress']"
                hint="Implementasi sedang berjalan" tone="indigo">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M11.42 6.42a4.5 4.5 0 0 0 6.16 6.16l3.17 3.17a2.25 2.25 0 0 1-3.18 3.18l-3.17-3.17a4.5 4.5 0 0 1-6.16-6.16l2.12 2.12 3.18-3.18-2.12-2.12Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Menunggu Evaluasi" :value="$stats['waiting_evaluation']"
                hint="Perlu evaluasi dan penutupan" tone="amber">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M12 8.25v3.75l2.25 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Ditutup" :value="$stats['closed']" hint="RFC telah ditutup"
                tone="emerald">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0 1 19.5 12.75v6A2.25 2.25 0 0 1 17.25 21H6.75a2.25 2.25 0 0 1-2.25-2.25v-6a2.25 2.25 0 0 1 2.25-2.25Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>
        </div>
    @else
        <div class="dashboard-rise dashboard-rise-1 grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-dashboard.stat-card label="Total RFC" :value="$stats['total']" hint="Seluruh RFC yang tercatat"
                tone="slate">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M8.25 6.75h9M8.25 12h9M8.25 17.25h9M4.5 6.75h.008v.008H4.5V6.75Zm0 5.25h.008v.008H4.5V12Zm0 5.25h.008v.008H4.5v-.008Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Menunggu Persetujuan" :value="$stats['pending_approval']"
                :percent="$statusPercent($stats['pending_approval'])" hint="Menunggu keputusan Change Manager"
                tone="amber">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M12 8.25v3.75l2.25 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Disetujui" :value="$stats['approved']"
                :percent="$statusPercent($stats['approved'])" hint="Sudah disetujui untuk dilaksanakan"
                tone="emerald">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card :label="\App\Enums\RfcStatus::groupLabel('rejected')" :value="$stats['rejected']"
                :percent="$statusPercent($stats['rejected'])" hint="Ditolak atau tidak terlaksana" tone="rose">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9.75 9.75l4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>

            <x-dashboard.stat-card label="Emergency" :value="$stats['emergency']"
                :percent="$statusPercent($stats['emergency'])" hint="Perubahan darurat" tone="violet">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z" />
                    </svg>
                </x-slot:icon>
            </x-dashboard.stat-card>
        </div>
    @endif

    {{-- Panel daftar RFC --}}
    <x-dashboard.panel class="dashboard-rise dashboard-rise-2" id="daftar-rfc"
        :title="$listTitles[$roleView] ?? 'Daftar Permintaan Perubahan (RFC)'"
        :description="$listDescriptions[$roleView] ?? $listDescriptions['change_manager']">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M8.25 6.75h9M8.25 12h9M8.25 17.25h9M4.5 6.75h.008v.008H4.5V6.75Zm0 5.25h.008v.008H4.5V12Zm0 5.25h.008v.008H4.5v-.008Z" />
            </svg>
        </x-slot:icon>

        <x-slot:actions>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <div class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                        </svg>
                    </span>
                    <input wire:model.live.debounce.300ms="search" type="text" aria-label="Cari RFC"
                        placeholder="Cari No. RFC / Judul..."
                        class="h-9 w-56 rounded-xl border border-slate-200 bg-slate-50/70 pl-9 pr-8 text-xs text-slate-700 placeholder:text-slate-400 focus:border-blue-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100 sm:w-64">
                    @if (filled($search))
                        <button type="button" wire:click="$set('search', '')"
                            class="absolute right-2 top-1/2 grid h-5 w-5 -translate-y-1/2 place-items-center rounded-full text-slate-400 transition hover:bg-slate-200 hover:text-slate-600"
                            aria-label="Bersihkan pencarian">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    @endif
                </div>

                @if ($roleView === 'change_manager')
                    @can('exportList', \App\Models\ChangeRequest::class)
                        <details class="relative">
                            <summary
                                class="flex cursor-pointer list-none items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-blue-300 hover:text-blue-700 [&::-webkit-details-marker]:hidden">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Ekspor
                            </summary>
                            <div
                                class="absolute right-0 z-30 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                                <p class="px-2 pb-2 text-[10px] leading-relaxed text-slate-500">
                                    Mengikuti filter yang sedang aktif.
                                </p>
                                <button type="button" wire:click="export('xlsx')"
                                    class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M14 3v4a1 1 0 0 0 1 1h4M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M9 13h6M9 17h6" />
                                    </svg>
                                    Excel (.xlsx)
                                </button>
                                <button type="button" wire:click="export('csv')"
                                    class="flex w-full flex-col items-start gap-0.5 rounded-lg px-2 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    <span class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                d="M7.5 3h9M7.5 21h9M4.5 8.25v7.5M19.5 8.25v7.5M8.25 8.25v7.5M15.75 8.25v7.5" />
                                        </svg>
                                        CSV (.csv)
                                    </span>
                                    <span class="pl-6 text-[10px] font-normal text-slate-400">Pemisah titik koma,
                                        cocok untuk Excel Indonesia.</span>
                                </button>
                            </div>
                        </details>
                    @endcan
                @endif
            </div>
        </x-slot:actions>

        {{-- Antrean buat tiket change --}}
        @if (auth()->user()?->can('rfc.review_completeness'))
            <div
                class="flex flex-col gap-2 border-b border-blue-100 bg-blue-50/60 px-5 py-3 text-xs sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 font-bold text-blue-900">
                        <svg class="h-3.5 w-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M3.75 7.5h16.5v9H3.75v-9Zm0 4.5h4.5l1.5 2.25h4.5l1.5-2.25h4.5" />
                        </svg>
                        Antrean Buat Tiket Change
                    </span>
                    @if ($queueFilter === 'incoming')
                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-semibold text-blue-700">
                            Diajukan &amp; dikembalikan ke Admin, terlama dulu
                        </span>
                    @endif
                </div>
                @if ($queueFilter === 'incoming')
                    <a href="{{ route('change-requests.index') }}"
                        class="font-semibold text-blue-700 hover:text-blue-900">
                        Tampilkan semua RFC
                    </a>
                @else
                    <a href="{{ route('change-requests.index', ['queue' => 'incoming']) }}"
                        class="font-semibold text-blue-700 hover:text-blue-900">
                        Buka antrean buat tiket &rarr;
                    </a>
                @endif
            </div>
        @endif

        {{-- Filter lanjutan --}}
        <div class="border-b border-slate-100 bg-slate-50/60"
            x-data="{ filtersOpen: {{ $activeFilterCount > 0 ? 'true' : 'false' }} }">
            <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-blue-300 hover:text-blue-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-100">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M4 6h16M7 12h10M10 18h4" />
                        </svg>
                        Filter lanjutan
                        @if ($activeFilterCount > 0)
                            <span
                                class="rounded-full bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700">{{ $activeFilterCount }}</span>
                        @endif
                        <svg class="h-3.5 w-3.5 transition-transform" x-bind:class="filtersOpen ? 'rotate-180' : ''"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
                        </svg>
                    </button>

                    @foreach ($filterChips as $chip)
                        <button type="button" wire:click="$set('{{ $chip['property'] }}', '')"
                            wire:key="filter-chip-{{ $chip['property'] }}"
                            class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-blue-800 shadow-sm transition hover:border-blue-300 hover:bg-blue-50"
                            title="Hapus filter {{ $chip['label'] }}">
                            <span class="font-bold uppercase tracking-wide text-blue-500">{{ $chip['label'] }}</span>
                            <span class="max-w-[9rem] truncate">{{ $chip['value'] }}</span>
                            <svg class="h-3 w-3 text-blue-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" wire:click="resetFilters"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-blue-300 hover:text-blue-700">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v6h6M20 20v-6h-6M20 9A8 8 0 0 0 5.3 5.3L4 10m16 4-1.3 4.7A8 8 0 0 1 4 15" />
                        </svg>
                        Reset filter
                    </button>
                </div>
            </div>

            <div x-show="filtersOpen" x-cloak x-transition.opacity.duration.150ms
                class="border-t border-slate-100 px-5 py-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <div>
                        <label for="filter-status"
                            class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Status</label>
                        <select id="filter-status" wire:model.live="statusFilter"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
                            <option value="">Semua status</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="filter-change-type"
                            class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Jenis
                            Perubahan</label>
                        <select id="filter-change-type" wire:model.live="categoryFilter"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
                            <option value="">Semua jenis</option>
                            @foreach ($changeTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="filter-application"
                            class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Aplikasi</label>
                        <select id="filter-application" wire:model.live="applicationFilter"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
                            <option value="">Semua aplikasi</option>
                            @foreach ($applications as $application)
                                <option value="{{ $application->id }}">{{ $application->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if (! in_array($roleView, ['pemohon', 'release_team'], true))
                        <div>
                            <label for="filter-opd"
                                class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Unit
                                Kerja Pemohon</label>
                            <select id="filter-opd" wire:model.live="opdFilter"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
                                <option value="">Semua unit</option>
                                @foreach ($opds as $opd)
                                    <option value="{{ $opd->id }}">{{ $opd->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label for="filter-date-from"
                            class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Dari
                            Tanggal</label>
                        <input id="filter-date-from" type="date" wire:model.live="dateFrom"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
                    </div>

                    <div>
                        <label for="filter-date-to"
                            class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Sampai
                            Tanggal</label>
                        <input id="filter-date-to" type="date" wire:model.live="dateTo"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
                    </div>
                </div>

                @error('dateFrom')
                    <p class="mt-2 text-[11px] font-medium text-rose-600">{{ $message }}</p>
                @enderror
                @error('dateTo')
                    <p class="mt-2 text-[11px] font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Tabel RFC --}}
        <div class="overflow-x-auto transition-opacity duration-200" wire:loading.class="opacity-60">
            <table class="rfc-responsive-table w-full min-w-[1080px] text-left text-xs text-slate-600">
                <caption class="sr-only">Daftar permintaan perubahan (RFC)</caption>
                <thead class="border-b border-slate-100 bg-slate-50/80 text-[10px] uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3.5 font-semibold">No. RFC</th>
                        <th scope="col" class="px-5 py-3.5 font-semibold">Judul Perubahan</th>
                        <th scope="col" class="px-5 py-3.5 font-semibold">Pemohon</th>
                        <th scope="col" class="px-5 py-3.5 font-semibold">Tanggal</th>
                        <th scope="col" class="px-5 py-3.5 font-semibold">Status</th>
                        <th scope="col" class="px-5 py-3.5 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/80">
                    @forelse($rfcs as $rfc)
                        @php
                            $statusVal = \App\Enums\RfcStatus::fromValue($rfc->status);
                            $requester = $rfc->submittedBy;
                            $requesterInitials = collect(explode(' ', trim((string) $requester?->name)))
                                ->filter()
                                ->take(2)
                                ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                                ->implode('');
                        @endphp
                        <tr wire:key="rfc-row-{{ $rfc->id }}"
                            class="group transition-colors duration-200 hover:bg-blue-50/40">
                            <td data-label="No. RFC" class="whitespace-nowrap px-5 py-4 align-top">
                                <span
                                    class="inline-flex items-center rounded-lg border border-blue-100 bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-800">
                                    {{ $rfc->displayIdentifier() }}
                                </span>
                            </td>
                            <td data-label="Judul Perubahan" class="max-w-[340px] px-5 py-4 align-top">
                                <p class="line-clamp-2 font-semibold leading-snug text-slate-800">{{ $rfc->title }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <x-category-badge :kategori="$rfc->change_type" size="sm" />
                                    @if (filled($rfc->application?->name))
                                        <span
                                            class="inline-flex max-w-[12rem] items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">
                                            <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.8"
                                                    d="M3.75 5.25h6l1.5 2.25h9v11.25H3.75V5.25Z" />
                                            </svg>
                                            <span class="truncate">{{ $rfc->application->name }}</span>
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Pemohon" class="px-5 py-4 align-top">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-blue-500 to-sky-500 text-[10px] font-bold uppercase text-white shadow-sm"
                                        aria-hidden="true">{{ $requesterInitials ?: '?' }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-slate-700">
                                            {{ $requester?->name ?? '-' }}</p>
                                        @if (filled($requester?->opd?->name))
                                            <p class="mt-0.5 truncate text-[11px] text-slate-400">
                                                {{ $requester->opd->name }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td data-label="Tanggal" class="whitespace-nowrap px-5 py-4 align-top">
                                <p class="inline-flex items-center gap-1.5 font-semibold text-slate-600">
                                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 5.25h13.5A1.5 1.5 0 0 1 20.25 6.75v12A1.5 1.5 0 0 1 18.75 20.25H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" />
                                    </svg>
                                    {{ $rfc->created_at?->copy()->locale('id')->translatedFormat('d M Y') }}
                                </p>
                                <p class="mt-0.5 text-[11px] text-slate-400">
                                    {{ $rfc->created_at?->copy()->locale('id')->diffForHumans() }}
                                </p>
                            </td>
                            <td data-label="Status" class="px-5 py-4 align-top">
                                <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
                                @if ($roleView === 'pemohon'
                                    && $statusVal === \App\Enums\RfcStatus::CLOSED
                                    && ! $rfc->satisfaction_exists)
                                    <span
                                        class="mt-1 inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                        Belum dinilai
                                    </span>
                                @endif
                            </td>
                            <td data-label="Aksi" class="whitespace-nowrap px-5 py-4 align-top">
                                <div class="flex flex-wrap items-center justify-start gap-2 md:justify-end">
                                    <a href="{{ route('change-requests.show', $rfc) }}"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-blue-300 hover:text-blue-700">
                                        Detail
                                    </a>

                                    @if ($roleView === 'pemohon' && auth()->user()?->can('update', $rfc))
                                        <a href="{{ route('change-requests.edit', $rfc) }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-amber-600">
                                            {{ $statusVal === \App\Enums\RfcStatus::NEEDS_REVISION ? 'Revisi' : 'Edit' }}
                                        </a>
                                    @endif

                                    @if ($roleView === 'pemohon' && $statusVal === \App\Enums\RfcStatus::DRAFT)
                                        <livewire:change-requests.delete-rfc :rfc="$rfc" variant="button"
                                            :key="'delete-rfc-' . $rfc->id" />
                                    @endif

                                    {{-- Aksi Admin / Frontdesk --}}
                                    @if ($roleView === 'admin' && in_array($statusVal, [\App\Enums\RfcStatus::SUBMITTED, \App\Enums\RfcStatus::RETURNED_TO_ADMIN], true))
                                        <a href="{{ route('change-requests.review', $rfc) }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-blue-600 to-sky-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-blue-500/20 transition duration-200 hover:-translate-y-0.5 hover:from-blue-700 hover:to-sky-700">
                                            Buat Tiket
                                        </a>
                                    @endif

                                    {{-- Aksi Change Manager --}}
                                    @if ($roleView === 'change_manager' && $statusVal === \App\Enums\RfcStatus::TICKET_CREATED)
                                        @can('recordDecision', $rfc)
                                            <a href="{{ route('change-requests.cm-review', $rfc) }}"
                                                class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                                                Tinjau
                                            </a>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8">
                                <x-dashboard.empty-state title="Belum ada RFC yang cocok"
                                    description="Belum ada dokumen RFC yang cocok dengan filter yang dipilih." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer daftar --}}
        <div
            class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[11px] text-slate-500">
                @if ($rfcs->total() > 0)
                    Menampilkan
                    <span class="font-semibold text-slate-700">{{ $rfcs->firstItem() }}–{{ $rfcs->lastItem() }}</span>
                    dari <span class="font-semibold text-slate-700">{{ $rfcs->total() }}</span> RFC
                @else
                    Tidak ada RFC untuk ditampilkan
                @endif
            </p>

            <div>
                {{ $rfcs->links() }}
            </div>
        </div>
    </x-dashboard.panel>
</div>
