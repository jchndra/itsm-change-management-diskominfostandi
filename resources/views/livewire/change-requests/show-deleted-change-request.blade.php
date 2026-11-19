<div class="space-y-6">

    {{-- Banner arsip --}}
    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 shadow-sm sm:px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-rose-100 text-rose-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v5M14 11v5" />
                    </svg>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-rose-800">RFC ini telah dihapus</h2>
                    <p class="mt-1 text-xs leading-relaxed text-rose-700">
                        Dihapus pada
                        <span class="font-semibold">
                            {{ $rfc->deleted_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                        </span>
                        oleh <span class="font-semibold">{{ $rfc->deletedBy?->name ?? 'pengguna tidak tercatat' }}</span>.
                        Data historis dan riwayat proses tetap tersimpan.
                    </p>
                    @if (filled($rfc->deletion_reason))
                        <p class="mt-2 whitespace-pre-wrap rounded-lg bg-white/70 px-3 py-2 text-xs leading-relaxed text-rose-900">
                            <span class="font-semibold">Alasan penghapusan:</span> {{ $rfc->deletion_reason }}
                        </p>
                    @endif
                </div>
            </div>

            <x-ui.button variant="secondary" :href="route('change-requests.deletions.index')" navigate size="sm">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0 7-7m-7 7h18" />
                </svg>
                Kembali ke Riwayat Penghapusan
            </x-ui.button>
        </div>
    </div>

    {{-- Tampilan detail RFC yang sudah ada (read-only) --}}
    @include('livewire.change-requests.show-change-request')
</div>
