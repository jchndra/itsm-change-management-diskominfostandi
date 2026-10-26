<div class="space-y-6">

    {{-- Judul halaman --}}
    <div>
        <h1 class="text-[26px] font-bold leading-tight tracking-tight text-slate-900 sm:text-[30px]">Dashboard Pemohon</h1>
        <p class="mt-1 text-sm text-slate-500">Pantau dan kelola permintaan perubahan layanan TI Anda.</p>
    </div>

    @if ($statusMessage)
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-800"
            role="status">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <p class="flex-1 font-medium">{{ $statusMessage }}</p>
            <button type="button" wire:click="$set('statusMessage', '')" class="text-emerald-500 hover:text-emerald-700"
                aria-label="Tutup notifikasi">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    {{-- Banner ajakan mengajukan RFC --}}
    <section
        class="relative overflow-hidden rounded-2xl border border-sky-100 bg-gradient-to-r from-sky-50 via-blue-50 to-sky-100 shadow-sm">
        <div class="relative z-10 flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex max-w-xl items-start gap-4">
                <span
                    class="grid h-14 w-14 shrink-0 place-items-center rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-600/25">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M14 3v4a1 1 0 0 0 1 1h4M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 13h6M9 17h4" />
                    </svg>
                </span>

                <div>
                    <h2 class="text-lg font-bold leading-snug text-slate-800 sm:text-xl">
                        Ajukan permintaan perubahan layanan TI
                    </h2>
                    <p class="mt-1.5 max-w-md text-sm leading-relaxed text-slate-600">
                        Sampaikan kebutuhan perubahan sistem atau infrastruktur TI untuk mendukung layanan di lingkungan
                        Diskominfostandi Kota Bekasi.
                    </p>
                    <a href="{{ route('change-requests.create') }}" wire:navigate
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-blue-700 to-blue-500 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-600/25 transition hover:from-blue-800 hover:to-blue-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4" />
                        </svg>
                        Ajukan RFC Baru
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>

            <div class="relative z-10 hidden items-center gap-3 lg:flex">
                <div class="rounded-xl bg-white/60 px-4 py-3 text-right shadow-sm ring-1 ring-white/70 backdrop-blur-sm">
                    <p class="text-sm font-semibold leading-snug text-blue-700">
                        Transformasi Digital<br>untuk Pelayanan Publik<br>yang Lebih Baik
                    </p>
                </div>
                <svg class="h-6 w-16 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 64 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M2 12h52m0 0-8-8m8 8-8 8" />
                </svg>
            </div>
        </div>

        {{-- Ilustrasi gedung pemerintahan --}}
        <div class="pointer-events-none absolute inset-y-0 right-0 hidden w-[42%] opacity-80 lg:block" aria-hidden="true">
            <svg viewBox="0 0 520 220" fill="none" preserveAspectRatio="xMaxYMax meet" class="h-full w-full">
                <defs>
                    <linearGradient id="hero-sky" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#dbeafe" stop-opacity="0" />
                        <stop offset="100%" stop-color="#bfdbfe" stop-opacity=".55" />
                    </linearGradient>
                    <linearGradient id="hero-building" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#ffffff" />
                        <stop offset="100%" stop-color="#dbeafe" />
                    </linearGradient>
                    <linearGradient id="hero-tower" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#e0f2fe" />
                        <stop offset="100%" stop-color="#bfdbfe" />
                    </linearGradient>
                    <linearGradient id="hero-fade" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="#eff6ff" stop-opacity="1" />
                        <stop offset="45%" stop-color="#eff6ff" stop-opacity="0" />
                    </linearGradient>
                </defs>

                <rect width="520" height="220" fill="url(#hero-sky)" />

                {{-- Siluet gedung belakang --}}
                <g fill="#bfdbfe" opacity=".65">
                    <rect x="292" y="96" width="30" height="124" />
                    <rect x="326" y="118" width="22" height="102" />
                    <rect x="352" y="82" width="34" height="138" />
                    <rect x="390" y="126" width="24" height="94" />
                    <rect x="418" y="102" width="28" height="118" />
                    <rect x="450" y="134" width="22" height="86" />
                    <rect x="476" y="112" width="30" height="108" />
                    <rect x="508" y="142" width="12" height="78" />
                </g>

                {{-- Menara utama --}}
                <g>
                    <rect x="238" y="46" width="52" height="174" fill="url(#hero-tower)" stroke="#93c5fd"
                        stroke-width="1" />
                    <path d="M238 46h52l-8-12h-36l-8 12Z" fill="#bfdbfe" stroke="#93c5fd" stroke-width="1" />
                    <rect x="250" y="20" width="28" height="14" rx="2" fill="#dbeafe" stroke="#93c5fd"
                        stroke-width="1" />
                    <path d="M264 4v16" stroke="#93c5fd" stroke-width="1.5" stroke-linecap="round" />
                    <circle cx="264" cy="4" r="2.5" fill="#60a5fa" />
                    <g fill="#bfdbfe" opacity=".85">
                        <rect x="248" y="62" width="6" height="10" rx="1" />
                        <rect x="261" y="62" width="6" height="10" rx="1" />
                        <rect x="274" y="62" width="6" height="10" rx="1" />
                        <rect x="248" y="80" width="6" height="10" rx="1" />
                        <rect x="261" y="80" width="6" height="10" rx="1" />
                        <rect x="274" y="80" width="6" height="10" rx="1" />
                        <rect x="248" y="98" width="6" height="10" rx="1" />
                        <rect x="261" y="98" width="6" height="10" rx="1" />
                        <rect x="274" y="98" width="6" height="10" rx="1" />
                    </g>
                    <rect x="256" y="128" width="16" height="20" rx="1" fill="#93c5fd" opacity=".7" />
                </g>

                {{-- Sayap gedung --}}
                <g>
                    <rect x="160" y="118" width="78" height="102" fill="url(#hero-building)" stroke="#bfdbfe"
                        stroke-width="1" />
                    <path d="M160 118h78l-10-14h-58l-10 14Z" fill="#e0f2fe" stroke="#bfdbfe"
                        stroke-width="1" />
                    <rect x="290" y="118" width="78" height="102" fill="url(#hero-building)" stroke="#bfdbfe"
                        stroke-width="1" />
                    <path d="M290 118h78l10-14h-58l-10 14Z" fill="#e0f2fe" stroke="#bfdbfe"
                        stroke-width="1" />

                    <g fill="#bfdbfe">
                        <rect x="170" y="132" width="8" height="16" rx="1" />
                        <rect x="184" y="132" width="8" height="16" rx="1" />
                        <rect x="198" y="132" width="8" height="16" rx="1" />
                        <rect x="212" y="132" width="8" height="16" rx="1" />
                        <rect x="170" y="158" width="8" height="16" rx="1" />
                        <rect x="184" y="158" width="8" height="16" rx="1" />
                        <rect x="198" y="158" width="8" height="16" rx="1" />
                        <rect x="212" y="158" width="8" height="16" rx="1" />
                        <rect x="300" y="132" width="8" height="16" rx="1" />
                        <rect x="314" y="132" width="8" height="16" rx="1" />
                        <rect x="328" y="132" width="8" height="16" rx="1" />
                        <rect x="342" y="132" width="8" height="16" rx="1" />
                        <rect x="300" y="158" width="8" height="16" rx="1" />
                        <rect x="314" y="158" width="8" height="16" rx="1" />
                        <rect x="328" y="158" width="8" height="16" rx="1" />
                        <rect x="342" y="158" width="8" height="16" rx="1" />
                    </g>

                    {{-- Pintu masuk --}}
                    <rect x="190" y="188" width="20" height="32" rx="2" fill="#93c5fd" opacity=".8" />
                    <rect x="318" y="188" width="20" height="32" rx="2" fill="#93c5fd" opacity=".8" />
                </g>

                {{-- Pohon --}}
                <g fill="#86efac">
                    <circle cx="146" cy="196" r="14" />
                    <circle cx="132" cy="204" r="11" />
                    <circle cx="380" cy="196" r="14" />
                    <circle cx="394" cy="204" r="11" />
                    <circle cx="424" cy="200" r="12" />
                </g>
                <g stroke="#64748b" stroke-width="3" stroke-linecap="round" opacity=".35">
                    <path d="M146 208v12M380 208v12M424 210v10" />
                </g>

                {{-- Tanah --}}
                <rect y="212" width="520" height="8" fill="#bfdbfe" opacity=".7" />

                {{-- Fade ke arah teks --}}
                <rect width="180" height="220" fill="url(#hero-fade)" />
            </svg>
        </div>
    </section>

    {{-- Kartu statistik --}}
    @php
        $cards = [
            [
                'label' => \App\Enums\RfcStatus::groupLabel('draft'),
                'value' => $stats['draft'],
                'hint' => 'RFC dalam draft',
                'wrapper' => 'border-slate-200 bg-slate-50/70 hover:border-slate-300',
                'icon' => 'bg-blue-100 text-blue-600',
                'path' => 'M14 3v4a1 1 0 0 0 1 1h4M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2ZM9 12h6M9 16h4',
                'chevron' => 'text-slate-400',
            ],
            [
                'label' => \App\Enums\RfcStatus::groupLabel('in_process'),
                'value' => $stats['in_process'],
                'hint' => 'Menunggu tindak lanjut',
                'wrapper' => 'border-blue-200 bg-blue-50/70 hover:border-blue-300',
                'icon' => 'bg-blue-100 text-blue-500',
                'path' => 'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                'chevron' => 'text-blue-400',
            ],
            [
                'label' => \App\Enums\RfcStatus::groupLabel('needs_revision'),
                'value' => $stats['needs_revision'],
                'hint' => 'Perlu diperbaiki pemohon',
                'wrapper' => 'border-amber-200 bg-amber-50/70 hover:border-amber-300',
                'icon' => 'bg-amber-100 text-amber-500',
                'path' => 'M12 9v4m0 4h.01M10.34 3.94l-8.4 14.53A1.5 1.5 0 0 0 3.23 21h17.54a1.5 1.5 0 0 0 1.3-2.53l-8.4-14.53a1.5 1.5 0 0 0-2.6 0Z',
                'chevron' => 'text-amber-400',
            ],
            [
                'label' => \App\Enums\RfcStatus::groupLabel('approved'),
                'value' => $stats['approved'],
                'hint' => 'Telah disetujui',
                'wrapper' => 'border-emerald-200 bg-emerald-50/70 hover:border-emerald-300',
                'icon' => 'bg-emerald-100 text-emerald-600',
                'path' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                'chevron' => 'text-emerald-400',
            ],
            [
                'label' => 'Ditolak',
                'value' => $stats['rejected'],
                'hint' => 'Ditolak atau tidak terlaksana',
                'wrapper' => 'border-rose-200 bg-rose-50/70 hover:border-rose-300',
                'icon' => 'bg-rose-100 text-rose-600',
                'path' => 'm9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                'chevron' => 'text-rose-400',
            ],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($cards as $card)
            <a href="{{ route('change-requests.index') }}" wire:navigate
                class="group flex h-full min-h-[112px] items-center gap-4 rounded-2xl border p-5 shadow-sm transition {{ $card['wrapper'] }}">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl {{ $card['icon'] }}">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="{{ $card['path'] }}" />
                    </svg>
                </span>

                <div class="min-w-0 flex-1">
                    <p class="whitespace-nowrap text-sm font-semibold text-slate-700">{{ $card['label'] }}</p>
                    <p class="mt-0.5 text-[26px] font-bold leading-none text-slate-800">{{ $card['value'] }}</p>
                    <p class="mt-1.5 text-[11px] text-slate-500">{{ $card['hint'] }}</p>
                </div>

                <svg class="h-4 w-4 shrink-0 {{ $card['chevron'] }} transition group-hover:translate-x-0.5" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="m9 5 7 7-7 7" />
                </svg>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-3">

        {{-- Tabel RFC terbaru --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
                <h3 class="flex items-center gap-2.5 text-[15px] font-bold text-slate-800">
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                            d="M14 3v4a1 1 0 0 0 1 1h4M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 12h6M9 16h4" />
                    </svg>
                    RFC Terbaru Saya
                </h3>
                <a href="{{ route('change-requests.index') }}" wire:navigate
                    class="text-xs font-semibold text-blue-600 transition hover:text-blue-700">Lihat Semua</a>
            </div>

            <div class="overflow-x-auto">
                <table class="rfc-responsive-table w-full min-w-[640px] text-left">
                    <thead class="border-b border-slate-100 bg-slate-50/70">
                        <tr class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3.5">No. RFC</th>
                            <th class="px-5 py-3.5">Judul Perubahan</th>
                            <th class="px-5 py-3.5">Tanggal</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentRfcs as $rfc)
                            <tr class="align-middle transition hover:bg-slate-50/60">
                                <td data-label="No. RFC" class="whitespace-nowrap px-5 py-4 text-[13px] font-bold text-blue-700">
                                    {{ $rfc->rfc_number ?? 'Draft' }}
                                </td>
                                <td data-label="Judul Perubahan" class="max-w-[260px] px-5 py-4 text-[13px] font-medium leading-snug text-slate-700">
                                    {{ $rfc->title }}
                                </td>
                                <td data-label="Tanggal" class="whitespace-nowrap px-5 py-4 text-[12px] leading-tight text-slate-500">
                                    {{ $rfc->created_at?->copy()->locale('id')->translatedFormat('d M Y') }}<br>
                                    <span class="text-slate-400">{{ $rfc->created_at?->copy()->locale('id')->translatedFormat('H:i') }}</span>
                                </td>
                                <td data-label="Status" class="whitespace-nowrap px-5 py-4">
                                    <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
                                </td>
                                <td data-label="Aksi" class="whitespace-nowrap px-5 py-4">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="{{ route('change-requests.show', $rfc) }}" wire:navigate
                                            class="inline-flex items-center rounded-lg border border-blue-300 bg-white px-3.5 py-2 text-xs font-semibold text-blue-700 transition hover:border-blue-400 hover:bg-blue-50">
                                            Lihat Detail
                                        </a>

                                        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                                            <button type="button" x-on:click="open = !open"
                                                class="grid h-8 w-7 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                                aria-label="Aksi lainnya">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <circle cx="10" cy="4" r="1.6" />
                                                    <circle cx="10" cy="10" r="1.6" />
                                                    <circle cx="10" cy="16" r="1.6" />
                                                </svg>
                                            </button>

                                            <div x-show="open" x-cloak x-transition.origin.top.right
                                                class="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                                                <a href="{{ route('change-requests.show', $rfc) }}" wire:navigate
                                                    class="block px-3.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">Lihat Detail</a>
                                                @if (in_array(\App\Enums\RfcStatus::fromValue($rfc->status), [\App\Enums\RfcStatus::DRAFT, \App\Enums\RfcStatus::NEEDS_REVISION], true))
                                                    <a href="{{ route('change-requests.edit', $rfc) }}" wire:navigate
                                                        class="block px-3.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                                                        {{ \App\Enums\RfcStatus::fromValue($rfc->status) === \App\Enums\RfcStatus::NEEDS_REVISION ? 'Revisi RFC' : 'Edit RFC' }}
                                                    </a>
                                                @endif
                                                @if (\App\Enums\RfcStatus::fromValue($rfc->status) === \App\Enums\RfcStatus::DRAFT)
                                                    <livewire:change-requests.delete-rfc :rfc="$rfc" variant="menu"
                                                        :key="'delete-rfc-' . $rfc->id" />
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center">
                                    <span
                                        class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-400">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                                d="M14 3v4a1 1 0 0 0 1 1h4M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                                        </svg>
                                    </span>
                                    <p class="mt-3 text-sm font-medium text-slate-600">Belum ada permintaan perubahan
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">RFC yang Anda ajukan akan muncul di sini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Kolom kanan --}}
        <div class="flex flex-col gap-6">

            {{-- Timeline status --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" wire:poll.30s
                aria-label="Status permohonan, diperbarui otomatis setiap 30 detik">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="flex items-center gap-2.5 text-[15px] font-bold text-slate-800">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                d="M9 12h6m-6 4h6M9 8h6M6 3h9l4 4v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                        </svg>
                        Status Permohonan Saya
                    </h3>

                    <div class="flex items-center gap-2">
                        <span title="Diperbarui otomatis setiap 30 detik"
                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">
                            <span class="relative flex h-1.5 w-1.5" aria-hidden="true">
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-70"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            </span>
                            Otomatis
                        </span>

                        @if ($latestActiveRfc)
                            <a href="{{ route('change-requests.show', $latestActiveRfc) }}" wire:navigate
                                class="text-xs font-semibold text-blue-600 transition hover:text-blue-700">Lihat Semua</a>
                        @endif
                    </div>
                </div>

                @if ($latestActiveRfc)
                    <p class="mb-4 inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                        {{ $latestActiveRfc->rfc_number }}
                    </p>

                    <ol class="relative space-y-5 before:absolute before:bottom-3 before:left-[13px] before:top-3 before:w-px before:bg-slate-200">
                        @foreach ($timeline as $step)
                            @php
                                $state = $step['state'];
                                $circle = match ($state) {
                                    'done' => 'bg-emerald-500 text-white ring-emerald-100',
                                    'danger' => 'bg-rose-500 text-white ring-rose-100',
                                    'warning' => 'bg-amber-500 text-white ring-amber-100',
                                    'current' => 'bg-blue-600 text-white ring-blue-100',
                                    default => 'bg-slate-100 text-slate-400 ring-white',
                                };
                                $title = match ($state) {
                                    'pending' => 'text-slate-400',
                                    'current' => 'text-blue-700',
                                    default => 'text-slate-800',
                                };
                            @endphp

                            <li class="relative flex gap-3.5">
                                <span
                                    class="relative z-10 grid h-[27px] w-[27px] shrink-0 place-items-center rounded-full ring-4 {{ $circle }}">
                                    @if ($state === 'done')
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                d="m5 13 4 4L19 7" />
                                        </svg>
                                    @elseif ($state === 'danger')
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    @elseif ($state === 'warning')
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"
                                                d="M12 9v4m0 4h.01" />
                                        </svg>
                                    @elseif ($state === 'current')
                                        <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-white"></span>
                                    @else
                                        <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                                    @endif
                                </span>

                                <div class="min-w-0 pt-0.5">
                                    <p class="text-[13px] font-semibold leading-tight {{ $title }}">{{ $step['label'] }}</p>
                                    <p class="mt-0.5 text-[11px] leading-snug {{ $state === 'current' ? 'text-blue-600' : 'text-slate-500' }}">
                                        {{ $step['description'] }}
                                    </p>
                                    @if ($step['time'])
                                        <p class="mt-0.5 text-[10px] text-slate-400">
                                            {{ $step['time']->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                        </p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <div class="py-6 text-center">
                        <p class="text-sm text-slate-500">Tidak ada RFC aktif yang sedang diproses.</p>
                        <a href="{{ route('change-requests.create') }}" wire:navigate
                            class="mt-2 inline-block text-xs font-semibold text-blue-600 hover:text-blue-700">Ajukan RFC
                            baru</a>
                    </div>
                @endif
            </section>

            {{-- Notifikasi terbaru --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="flex items-center gap-2.5 text-[15px] font-bold text-slate-800">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                        Notifikasi Terbaru
                    </h3>
                    <a href="{{ route('notifications.index') }}" wire:navigate
                        class="text-xs font-semibold text-blue-600 transition hover:text-blue-700">Lihat Semua</a>
                </div>

                <div class="space-y-3.5">
                    @forelse ($notifications as $notification)
                        <a href="{{ route('notifications.index', ['open' => $notification->id]) }}"
                            class="flex items-start gap-3">
                            <x-notification-icon :status="data_get($notification->data, 'status')" :outcome="data_get($notification->data, 'outcome')" class="h-8 w-8" />
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 text-[12px] leading-snug text-slate-700">
                                    {{ data_get($notification->data, 'message', 'Notifikasi baru') }}
                                </p>
                                <p class="mt-0.5 text-[10px] text-slate-400">
                                    {{ $notification->created_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                    &middot;
                                    {{ $notification->created_at?->copy()->locale('id')->diffForHumans() }}
                                </p>
                            </div>
                        </a>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">Belum ada notifikasi baru.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
