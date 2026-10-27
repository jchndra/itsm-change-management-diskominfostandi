<div class="space-y-6">
    @php
        $showTicketColumn = $roleView !== 'pemohon'
            && $rfcs->getCollection()->contains(fn ($rfc): bool => filled($rfc->ticket_number));
    @endphp

    {{-- Header Banner & Ucapan Selamat Datang --}}
    <div
        class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-blue-200">
                    Diskominfostandi Kota Bekasi
                </span>
                <h1 class="text-2xl font-bold mt-1">
                    Selamat Datang, {{ auth()->user()->name }}
                </h1>
                <p class="text-sm text-blue-100/80 mt-1 max-w-xl">
                    @if ($roleView === 'pemohon')
                        "Jadikan setiap perubahan sebagai langkah menuju layanan publik yang lebih baik."
                    @elseif($roleView === 'admin')
                        "Kelola penerimaan dan verifikasi administrasi dokumen perubahan secara teliti dan cepat."
                    @elseif($roleView === 'release_team')
                        "Kerjakan RFC yang ditugaskan dengan bukti pelaksanaan yang lengkap dan dapat diaudit."
                    @else
                        "Kelola perubahan layanan TI secara aman, terukur, transparan, dan terdokumentasi."
                    @endif
                </p>
            </div>

            @if ($roleView === 'pemohon')
                <div>
                    <a href="{{ route('change-requests.create') }}"
                        class="inline-flex items-center px-5 py-2.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold rounded-xl shadow transition transform hover:-translate-y-0.5">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Ajukan RFC Baru
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- STATS CARDS --}}
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

    @if ($roleView === 'pemohon')
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-medium text-slate-500">RFC Saya</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ $stats['my_rfc'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-medium text-blue-600">{{ \App\Enums\RfcStatus::groupLabel('in_process') }}</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ $stats['in_process'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-medium text-amber-600">{{ \App\Enums\RfcStatus::groupLabel('needs_revision') }}</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['needs_revision'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-medium text-emerald-600">{{ \App\Enums\RfcStatus::groupLabel('approved') }}</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $stats['approved'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-medium text-rose-600">{{ \App\Enums\RfcStatus::groupLabel('rejected') }}</p>
                <p class="text-2xl font-bold text-rose-600 mt-1">{{ $stats['rejected'] }}</p>
            </div>
        </div>
    @elseif($roleView === 'admin')
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('change-requests.index', ['queue' => 'incoming']) }}"
                class="group bg-white p-5 rounded-2xl border border-blue-100 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                <p class="text-xs font-medium text-blue-600">RFC Masuk</p>
                <p class="text-2xl font-bold text-blue-900 mt-1">{{ $stats['incoming'] }}</p>
                <p class="mt-2 text-[11px] font-semibold text-blue-500 group-hover:text-blue-700">Buka antrean buat tiket &rarr;</p>
            </a>
            <div class="bg-white p-5 rounded-2xl border border-amber-100 shadow-sm">
                <p class="text-xs font-medium text-amber-600">Perlu Revisi</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['needs_revision'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-emerald-100 shadow-sm">
                <p class="text-xs font-medium text-emerald-600">Diteruskan</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $stats['forwarded'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-rose-100 shadow-sm">
                <p class="text-xs font-medium text-rose-600">{{ \App\Enums\RfcStatus::groupLabel('rejected') }}</p>
                <p class="text-2xl font-bold text-rose-600 mt-1">{{ $stats['rejected'] }}</p>
            </div>
        </div>
    @elseif($roleView === 'release_team')
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-blue-100 shadow-sm">
                <p class="text-xs font-medium text-blue-600">RFC Ditugaskan</p>
                <p class="text-2xl font-bold text-blue-900 mt-1">{{ $stats['assigned'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-indigo-100 shadow-sm">
                <p class="text-xs font-medium text-indigo-600">Sedang Dikerjakan</p>
                <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $stats['in_progress'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-amber-100 shadow-sm">
                <p class="text-xs font-medium text-amber-600">Menunggu Evaluasi</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['waiting_evaluation'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-emerald-100 shadow-sm">
                <p class="text-xs font-medium text-emerald-600">Ditutup</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $stats['closed'] }}</p>
            </div>
        </div>
    @else
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-medium text-slate-500">Total RFC</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ $stats['total'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-amber-100 shadow-sm">
                <p class="text-xs font-medium text-amber-600">Menunggu Persetujuan</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['pending_approval'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-emerald-100 shadow-sm">
                <p class="text-xs font-medium text-emerald-600">Disetujui</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $stats['approved'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-rose-100 shadow-sm">
                <p class="text-xs font-medium text-rose-600">{{ \App\Enums\RfcStatus::groupLabel('rejected') }}</p>
                <p class="text-2xl font-bold text-rose-600 mt-1">{{ $stats['rejected'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-purple-100 shadow-sm">
                <p class="text-xs font-medium text-purple-600">Emergency</p>
                <p class="text-2xl font-bold text-purple-600 mt-1">{{ $stats['emergency'] }}</p>
            </div>
        </div>
    @endif

    {{-- TABEL KONTEN UTAMA --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        @php
            $activeFilterCount = collect([
                $statusFilter,
                $categoryFilter,
                $applicationFilter,
                $opdFilter,
                $dateFrom,
                $dateTo,
            ])->filter(fn ($value) => filled($value))->count();
        @endphp

        <div class="p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-slate-800">Daftar Permintaan Perubahan (RFC)</h3>
                <p class="text-xs text-slate-500">Pantau dan kelola seluruh usulan perubahan sistem informasi</p>
            </div>

            <div class="flex items-center gap-3">
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="Cari No. RFC{{ $showTicketColumn ? ' / No. Tiket' : '' }} / Judul..."
                    class="px-3.5 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none w-48 md:w-64">
                @if ($activeFilterCount > 0)
                    <span class="hidden rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-bold text-blue-700 sm:inline">
                        {{ $activeFilterCount }} filter aktif
                    </span>
                @endif
            </div>
        </div>

        @if (auth()->user()?->can('rfc.review_completeness'))
            <div class="flex flex-col gap-2 border-b border-slate-100 bg-blue-50/60 px-5 py-3 text-xs sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-bold text-blue-900">Antrean Buat Tiket Change</span>
                    @if ($queueFilter === 'incoming')
                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-semibold text-blue-700">
                            Diajukan &amp; dikembalikan ke Admin, terlama dulu
                        </span>
                    @endif
                </div>
                @if ($queueFilter === 'incoming')
                    <a href="{{ route('change-requests.index') }}" class="font-semibold text-blue-700 hover:text-blue-900">
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

        {{-- Panel filter lanjutan --}}
        <div class="grid grid-cols-1 gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div>
                <label for="filter-status" class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Status</label>
                <select id="filter-status" wire:model.live="statusFilter"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-change-type" class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Jenis Perubahan</label>
                <select id="filter-change-type" wire:model.live="categoryFilter"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
                    <option value="">Semua jenis</option>
                    @foreach ($changeTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-application" class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Aplikasi</label>
                <select id="filter-application" wire:model.live="applicationFilter"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
                    <option value="">Semua aplikasi</option>
                    @foreach ($applications as $application)
                        <option value="{{ $application->id }}">{{ $application->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($roleView !== 'pemohon')
                <div>
                    <label for="filter-opd" class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Unit Kerja Pemohon</label>
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
                <label for="filter-date-from" class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Dari Tanggal</label>
                <input id="filter-date-from" type="date" wire:model.live="dateFrom"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
            </div>

            <div>
                <label for="filter-date-to" class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">Sampai Tanggal</label>
                <input id="filter-date-to" type="date" wire:model.live="dateTo"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
            </div>

            <div class="flex items-end sm:col-span-2 lg:col-span-3 xl:col-span-6">
                <button type="button" wire:click="resetFilters"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 transition hover:border-blue-300 hover:text-blue-700">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v6h6M20 20v-6h-6M20 9A8 8 0 0 0 5.3 5.3L4 10m16 4-1.3 4.7A8 8 0 0 1 4 15" />
                    </svg>
                    Reset filter
                </button>
                @error('dateFrom')
                    <span class="ml-3 text-[11px] font-medium text-rose-600">{{ $message }}</span>
                @enderror
                @error('dateTo')
                    <span class="ml-3 text-[11px] font-medium text-rose-600">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="rfc-responsive-table w-full text-left text-xs text-slate-600">
                <thead
                    class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="px-5 py-3">No. RFC</th>
                        @if ($showTicketColumn)
                            <th class="px-5 py-3">No. Tiket</th>
                        @endif
                        <th class="px-5 py-3">Judul Perubahan</th>
                        <th class="px-5 py-3">Pemohon</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rfcs as $rfc)
                        @php
                            $statusVal = \App\Enums\RfcStatus::fromValue($rfc->status);
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <td data-label="No. RFC" class="px-5 py-3.5 font-bold text-blue-900">
                                {{ $rfc->displayIdentifier() }}
                            </td>
                            @if ($showTicketColumn)
                                <td data-label="No. Tiket" class="px-5 py-3.5 font-semibold text-slate-700">
                                    {{ filled($rfc->ticket_number) ? $rfc->ticket_number : 'Belum dibuat' }}
                                </td>
                            @endif
                            <td data-label="Judul Perubahan" class="px-5 py-3.5 font-medium text-slate-800">
                                {{ $rfc->title }}
                            </td>
                            <td data-label="Pemohon" class="px-5 py-3.5 text-slate-600">
                                {{ $rfc->submittedBy?->name ?? '-' }}
                            </td>
                            <td data-label="Tanggal" class="px-5 py-3.5 text-slate-500">
                                {{ $rfc->created_at?->copy()->locale('id')->translatedFormat('d M Y') }}
                                <span class="block text-[11px] text-slate-400">
                                    {{ $rfc->created_at?->copy()->locale('id')->diffForHumans() }}
                                </span>
                            </td>
                            <td data-label="Status" class="px-5 py-3.5">
                                <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
                            </td>
                            <td data-label="Aksi" class="px-5 py-3.5 text-right space-x-2">
                                <a href="{{ route('change-requests.show', $rfc) }}"
                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-lg text-xs transition">
                                    Detail
                                </a>

                                @if ($roleView === 'pemohon' && auth()->user()?->can('update', $rfc))
                                    <a href="{{ route('change-requests.edit', $rfc) }}"
                                        class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white font-medium rounded-lg text-xs transition">
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
                                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-xs transition">
                                        Buat Tiket
                                    </a>
                                @endif

                                {{-- Aksi Change Manager --}}
                                @if ($roleView === 'change_manager' && $statusVal === \App\Enums\RfcStatus::TICKET_CREATED)
                                    @if ($rfc->riskAssessment)
                                        <a href="{{ route('change-requests.record-decision', $rfc) }}"
                                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg text-xs transition">
                                            Keputusan Change Manager
                                        </a>
                                    @else
                                        <a href="{{ route('change-requests.assess-risk', $rfc) }}"
                                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-xs transition">
                                            Evaluasi
                                        </a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showTicketColumn ? 7 : 6 }}" class="px-5 py-8 text-center text-slate-400">
                                Belum ada dokumen RFC yang cocok dengan filter yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100">
            {{ $rfcs->links() }}
        </div>
    </div>
</div>
