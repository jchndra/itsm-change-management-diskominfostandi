<div class="space-y-6">

    {{-- Judul halaman --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-200/70 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v5M14 11v5" />
                </svg>
                Arsip RFC
            </span>
            <h1 class="mt-2 text-[26px] font-bold leading-tight tracking-tight text-slate-900 sm:text-[30px]">
                Riwayat Penghapusan
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                @if ($isStaff)
                    Daftar RFC yang dihapus beserta alasannya. RFC tidak dihapus permanen dan dapat dipulihkan ke
                    daftar aktif.
                @else
                    Daftar RFC milik Anda yang dihapus beserta alasannya. Data tetap tersimpan dan dapat dipulihkan
                    oleh Admin atau Change Manager.
                @endif
            </p>
        </div>

        <a href="{{ route('change-requests.index') }}" wire:navigate
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-blue-300 hover:text-blue-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M14 3v4a1 1 0 0 0 1 1h4M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6M9 16h4" />
            </svg>
            Lihat RFC Aktif
        </a>
    </div>

    @if ($feedback)
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-800"
            role="status">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <p class="flex-1 font-medium">{{ $feedback }}</p>
            <button type="button" wire:click="$set('feedback', '')" class="text-emerald-500 hover:text-emerald-700"
                aria-label="Tutup notifikasi">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-800"
            role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v4m0 4h.01M10.34 3.94l-8.4 14.53A1.5 1.5 0 0 0 3.23 21h17.54a1.5 1.5 0 0 0 1.3-2.53l-8.4-14.53a1.5 1.5 0 0 0-2.6 0Z" />
            </svg>
            <p class="flex-1 font-medium">{{ $errorMessage }}</p>
            <button type="button" wire:click="$set('errorMessage', '')" class="text-rose-500 hover:text-rose-700"
                aria-label="Tutup pesan kesalahan">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    {{-- Filter --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label for="deletion-search" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    Cari RFC
                </label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                        </svg>
                    </span>
                    <input id="deletion-search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Nomor RFC atau judul perubahan..."
                        class="w-full rounded-xl border border-slate-200 bg-slate-50/60 py-2.5 pl-10 pr-3.5 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100">
                </div>
            </div>

            <div>
                <label for="deletion-date-from" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    Tanggal Dari
                </label>
                <input id="deletion-date-from" type="date" wire:model.live="dateFrom"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 py-2.5 text-sm text-slate-700 focus:border-blue-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100">
            </div>

            <div>
                <label for="deletion-date-to" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    Tanggal Sampai
                </label>
                <input id="deletion-date-to" type="date" wire:model.live="dateTo"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 py-2.5 text-sm text-slate-700 focus:border-blue-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100">
            </div>

            <div>
                <label for="deletion-status" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    Status Saat Dihapus
                </label>
                <select id="deletion-status" wire:model.live="statusFilter"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 py-2.5 text-sm text-slate-700 focus:border-blue-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>
                Menampilkan <span class="font-semibold text-slate-700">{{ $deletedRfcs->count() }}</span> dari
                <span class="font-semibold text-slate-700">{{ $deletedRfcs->total() }}</span> RFC terhapus.
            </p>
            <div class="flex items-center gap-3">
                @error('dateFrom')
                    <span class="font-medium text-rose-600">{{ $message }}</span>
                @enderror
                @error('dateTo')
                    <span class="font-medium text-rose-600">{{ $message }}</span>
                @enderror
                <button type="button" wire:click="resetFilters"
                    class="font-semibold text-blue-600 transition hover:text-blue-700">Reset filter</button>
            </div>
        </div>
    </section>

    {{-- Tabel --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="rfc-responsive-table w-full min-w-[860px] text-left">
                <thead class="border-b border-slate-100 bg-slate-50/70">
                    <tr class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-5 py-3.5">No. RFC</th>
                        <th class="px-5 py-3.5">Judul Perubahan</th>
                        <th class="px-5 py-3.5">Tanggal Penghapusan</th>
                        <th class="px-5 py-3.5">Penghapus</th>
                        <th class="px-5 py-3.5">Alasan Penghapusan</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($deletedRfcs as $rfc)
                        <tr wire:key="deleted-rfc-{{ $rfc->id }}" class="align-top transition hover:bg-slate-50/60">
                            <td data-label="No. RFC" class="whitespace-nowrap px-5 py-4 text-[13px] font-bold text-blue-800">
                                {{ $rfc->displayIdentifier() }}
                            </td>
                            <td data-label="Judul Perubahan" class="max-w-[260px] px-5 py-4">
                                <p class="text-[13px] font-medium leading-snug text-slate-700">{{ $rfc->title }}</p>
                                <p class="mt-1 text-[11px] text-slate-400">
                                    {{ $rfc->application?->name ?? 'Aplikasi tidak tercatat' }}
                                </p>
                            </td>
                            <td data-label="Tanggal Penghapusan" class="whitespace-nowrap px-5 py-4 text-[12px] leading-tight text-slate-500">
                                {{ $rfc->deleted_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                            </td>
                            <td data-label="Penghapus" class="whitespace-nowrap px-5 py-4 text-[12px] text-slate-600">
                                {{ $rfc->deletedBy?->name ?? 'Tidak tercatat' }}
                            </td>
                            <td data-label="Alasan Penghapusan" class="max-w-[240px] px-5 py-4">
                                <p class="whitespace-pre-wrap text-[12px] leading-snug text-slate-600">
                                    {{ $rfc->deletion_reason ?: 'Tidak diisi' }}
                                </p>
                            </td>
                            <td data-label="Aksi" class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('change-requests.deletions.show', $rfc) }}" wire:navigate
                                        class="inline-flex items-center rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:border-blue-400 hover:bg-blue-50">
                                        Lihat Detail
                                    </a>

                                    @if (auth()->user()?->can('restore', $rfc))
                                        <button type="button" wire:click="confirmRestore({{ $rfc->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2" d="M4 4v6h6M20 20v-6h-6" />
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2" d="M20 9A8 8 0 0 0 5.3 5.3L4 10m16 4-1.3 4.7A8 8 0 0 1 4 15" />
                                            </svg>
                                            Pulihkan
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-14 text-center">
                                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                            d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v5M14 11v5" />
                                    </svg>
                                </span>
                                <p class="mt-3 text-sm font-semibold text-slate-600">Belum ada RFC yang dihapus</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    @if ($isStaff)
                                        RFC Draft yang dihapus oleh pemohon akan muncul di halaman ini.
                                    @else
                                        RFC Draft yang Anda hapus akan muncul di halaman ini.
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($deletedRfcs->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">
                {{ $deletedRfcs->links() }}
            </div>
        @endif
    </section>

    {{-- Dialog konfirmasi pemulihan --}}
    @if ($showRestoreModal && $selectedRfc)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
            role="dialog" aria-modal="true" aria-labelledby="restore-rfc-title"
            aria-describedby="restore-rfc-description" wire:click.self="closeRestoreModal"
            wire:keydown.escape.window="closeRestoreModal">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-4">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v6h6M20 20v-6h-6" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 9A8 8 0 0 0 5.3 5.3L4 10m16 4-1.3 4.7A8 8 0 0 1 4 15" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 id="restore-rfc-title" class="text-base font-bold text-slate-800">Pulihkan RFC?</h3>
                        <p id="restore-rfc-description" class="mt-0.5 text-xs text-slate-500">RFC akan kembali ke daftar
                            aktif dengan status terakhirnya.</p>
                    </div>
                </div>

                <div class="space-y-3 px-5 py-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Nomor RFC</p>
                        <p class="mt-0.5 text-sm font-bold text-blue-800">{{ $selectedRfc->displayIdentifier() }}</p>
                        <p class="mt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Judul
                            Perubahan</p>
                        <p class="mt-0.5 text-sm font-medium leading-snug text-slate-700">{{ $selectedRfc->title }}</p>
                    </div>

                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-800">
                        Status tidak akan diubah dan proses persetujuan tidak diulang. Status saat ini:
                        <span class="font-semibold">{{ \App\Enums\RfcStatus::labelFor($selectedRfc->status) }}</span>.
                    </div>

                    @if ($errorMessage)
                        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
                            {{ $errorMessage }}
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3.5">
                    <button type="button" wire:click="closeRestoreModal" autofocus
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800">
                        Batal
                    </button>
                    <button type="button" wire:click="restoreRfc" wire:loading.attr="disabled" wire:target="restoreRfc"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <svg wire:loading wire:target="restoreRfc" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24"
                            fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3"
                                stroke-width="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"
                                stroke-linecap="round" />
                        </svg>
                        Pulihkan RFC
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
