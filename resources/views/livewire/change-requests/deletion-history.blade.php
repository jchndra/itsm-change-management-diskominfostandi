<div class="space-y-6">

    {{-- Judul halaman --}}
    <x-ui.page-header eyebrow="Arsip RFC" title="Riwayat Penghapusan"
        :description="$isStaff
            ? 'Daftar RFC yang dihapus beserta alasannya. RFC tidak dihapus permanen dan dapat dipulihkan ke daftar aktif.'
            : 'Daftar RFC milik Anda yang dihapus beserta alasannya. Data tetap tersimpan dan dapat dipulihkan oleh Admin atau Change Manager.'"
        icon-tone="navy">
        <x-slot:icon>
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v5M14 11v5" />
            </svg>
        </x-slot:icon>

        <x-slot:meta>
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-semibold text-slate-600">
                {{ $deletedRfcs->total() }} RFC terhapus
            </span>
        </x-slot:meta>

        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('change-requests.index')" navigate size="sm">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M14 3v4a1 1 0 0 0 1 1h4M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6M9 16h4" />
                </svg>
                Lihat RFC Aktif
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($feedback)
        <x-ui.alert tone="success" dismiss="$set('feedback', '')">
            <span class="font-medium">{{ $feedback }}</span>
        </x-ui.alert>
    @endif

    @if ($errorMessage)
        <x-ui.alert tone="danger" dismiss="$set('errorMessage', '')">
            <span class="font-medium">{{ $errorMessage }}</span>
        </x-ui.alert>
    @endif

    {{-- Filter --}}
    <x-dashboard.panel title="Filter" description="Sempitkan arsip berdasarkan kata kunci, tanggal, dan status.">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                    d="M3 5.25h18M6.75 12h10.5M10.5 18.75h3" />
            </svg>
        </x-slot:icon>

        <div class="p-5 sm:p-6">
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

        <div class="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
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
                <x-ui.button variant="ghost" size="sm" wire:click="resetFilters">
                    Reset filter
                </x-ui.button>
            </div>
        </div>
        </div>
    </x-dashboard.panel>

    {{-- Tabel --}}
    <x-dashboard.panel title="Daftar RFC Terhapus"
        description="RFC tidak dihapus permanen dan dapat dipulihkan oleh yang berwenang.">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                    d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v5M14 11v5" />
            </svg>
        </x-slot:icon>

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
                            <td colspan="6" class="px-4 py-4">
                                <x-dashboard.empty-state title="Belum ada RFC yang dihapus"
                                    :description="$isStaff
                                        ? 'RFC Draft yang dihapus oleh pemohon akan muncul di halaman ini.'
                                        : 'RFC Draft yang Anda hapus akan muncul di halaman ini.'" />
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
    </x-dashboard.panel>

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
