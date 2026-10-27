<div class="max-w-5xl mx-auto space-y-6">
    @php
        $submittedCategory = match ((string) $rfc->change_type) {
            'standard' => 'Standar',
            'normal' => 'Normal',
            'emergency' => 'Emergency',
            default => '-',
        };
        $isReturnedToAdmin = $rfc->status === \App\Enums\RfcStatus::RETURNED_TO_ADMIN->value;
    @endphp

    {{-- Top Bar --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('change-requests.index', ['queue' => 'incoming']) }}"
            class="inline-flex items-center text-xs font-semibold text-slate-600 transition hover:text-blue-900">
            <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali ke Antrean Buat Tiket
        </a>
        <span class="text-xs font-medium text-slate-500">Buat Tiket Change (Admin / Frontdesk)</span>
    </div>

    {{-- Judul Halaman --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <span class="rounded-md bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-900">{{ $rfc->displayIdentifier() }}</span>
                <h1 class="mt-2 text-xl font-bold text-slate-900">Buat Tiket Change</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $rfc->title }}</p>
            </div>
            <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
        </div>

        <div class="mt-5 grid grid-cols-1 gap-4 text-xs sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-slate-400">Pemohon</p>
                <p class="mt-0.5 font-semibold text-slate-800">{{ $rfc->submittedBy?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-400">Unit Kerja</p>
                <p class="mt-0.5 font-semibold text-slate-800">{{ $rfc->submittedBy?->opd?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-400">Aplikasi</p>
                <p class="mt-0.5 font-semibold text-slate-800">{{ $rfc->application?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-slate-400">Target Tanggal Pelaksanaan</p>
                <p class="mt-0.5 font-semibold text-slate-800">
                    {{ $rfc->target_implementation_date?->copy()->locale('id')->translatedFormat('d M Y, H:i') ?? '-' }}
                    @if ($rfc->target_implementation_date)
                        WIB
                    @endif
                </p>
            </div>
            <div>
                <p class="text-slate-400">Kategori yang Diusulkan Pemohon</p>
                <p class="mt-0.5 font-semibold text-slate-800">{{ $submittedCategory }}</p>
            </div>
            <div>
                <p class="text-slate-400">Nota Dinas</p>
                <p class="mt-0.5 font-semibold text-slate-800">
                    {{ $rfc->is_physical_letter ? 'Ya' : 'Tidak' }}
                    @if ($rfc->is_physical_letter && filled($rfc->physical_letter_number))
                        <span class="block font-normal text-slate-600">{{ $rfc->physical_letter_number }}</span>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-slate-400">Tanggal Pengajuan</p>
                <p class="mt-0.5 font-semibold text-slate-800">
                    {{ $rfc->created_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }} WIB
                </p>
            </div>
        </div>

        <div class="mt-5 space-y-4 border-t border-slate-100 pt-4 text-xs">
            <div>
                <p class="font-semibold text-slate-500">Deskripsi Perubahan</p>
                <p class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-700">{{ $rfc->description ?? '-' }}</p>
            </div>
            <div>
                <p class="font-semibold text-slate-500">Alasan Bisnis / Alasan Perubahan</p>
                <p class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-700">{{ $rfc->reason_for_change ?? '-' }}</p>
            </div>
            <div>
                <p class="font-semibold text-slate-500">Estimasi Dampak</p>
                <p class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-700">{{ $rfc->impact_description ?? '-' }}</p>
            </div>
        </div>
    </div>

    @if ($rfc->emergency_unconfirmed)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-medium text-amber-800">
            Pemohon mengajukan kategori Emergency; Change Manager akan mengonfirmasi
        </div>
    @endif

    @if ($isReturnedToAdmin)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="text-sm font-bold text-amber-900">Catatan Change Manager yang Mengembalikan</h2>
            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-amber-800">{{ $returnNote ?: 'Tidak ada catatan yang tersedia.' }}</p>
        </div>
    @endif

    {{-- Lampiran --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="border-b border-slate-100 pb-3 text-sm font-bold text-slate-800">Daftar Lampiran</h2>
        @if ($rfc->attachments->isNotEmpty())
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($rfc->attachments as $attachment)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $attachment->original_name }}</p>
                            <p class="text-xs text-slate-400">{{ number_format($attachment->size / 1024, 0, ',', '.') }} KB</p>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-semibold">
                            <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]) }}"
                                target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-700">Lihat</a>
                            <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment, 'download' => 1]) }}"
                                class="text-indigo-600 hover:text-indigo-700">Unduh</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 text-xs text-slate-500">Tidak ada lampiran.</p>
        @endif
    </div>

    @if (filled($rfc->ticket_number))
        <div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-800">
            RFC ini sudah memiliki <strong>Nomor Tiket {{ $rfc->ticket_number }}</strong>. Nomor tersebut akan digunakan kembali saat tiket dikirim ulang ke Change Manager.
        </div>
    @endif

    {{-- Form Buat Tiket --}}
    <form wire:submit.prevent="createTicket" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <h2 class="text-sm font-bold text-slate-800">Penetapan Tiket Change</h2>
            <p class="mt-1 text-xs text-slate-500">Lengkapi prioritas, kategori layanan, dan checklist verifikasi sebelum meneruskan tiket ke Change Manager.</p>
        </div>

        @error('status')
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">{{ $message }}</div>
        @enderror

        <div class="grid grid-cols-1 gap-5 text-xs sm:grid-cols-2">
            <div class="space-y-2">
                <label for="priority" class="block font-bold text-slate-700">Prioritas <span class="text-rose-500">*</span></label>
                <select id="priority" wire:model="priority"
                    class="w-full rounded-xl border border-slate-200 p-3 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Pilih prioritas</option>
                    @foreach (\App\Enums\TicketPriority::cases() as $priorityOption)
                        <option value="{{ $priorityOption->value }}">{{ $priorityOption->label() }}</option>
                    @endforeach
                </select>
                @error('priority')
                    <span class="font-medium text-rose-600">{{ $message }}</span>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="category" class="block font-bold text-slate-700">Kategori Layanan <span class="text-rose-500">*</span></label>
                <select id="category" wire:model="category"
                    class="w-full rounded-xl border border-slate-200 p-3 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Pilih kategori layanan</option>
                    @foreach (\App\Enums\ServiceCategory::cases() as $categoryOption)
                        <option value="{{ $categoryOption->value }}">{{ $categoryOption->label() }}</option>
                    @endforeach
                </select>
                @error('category')
                    <span class="font-medium text-rose-600">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="space-y-2">
            <label for="admin_note" class="block text-xs font-bold text-slate-700">Catatan untuk Change Manager <span class="font-normal text-slate-400">(opsional, maksimal 1000 karakter)</span></label>
            <textarea id="admin_note" wire:model="admin_note" rows="4" maxlength="1000"
                placeholder="Tambahkan konteks atau hal penting yang perlu diketahui Change Manager..."
                class="w-full rounded-xl border border-slate-200 p-3 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            @error('admin_note')
                <span class="text-xs font-medium text-rose-600">{{ $message }}</span>
            @enderror
        </div>

        <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
            <h3 class="text-xs font-bold text-slate-800">Checklist Verifikasi</h3>
            <label class="flex items-start gap-3 text-xs text-slate-700">
                <input type="checkbox" wire:model="data_complete" class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span>Data RFC sudah lengkap</span>
            </label>
            @error('data_complete')
                <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror

            <label class="flex items-start gap-3 text-xs text-slate-700">
                <input type="checkbox" wire:model="attachments_verified" class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span>Lampiran dan nota dinas sudah sesuai</span>
            </label>
            @error('attachments_verified')
                <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror

            <label class="flex items-start gap-3 text-xs text-slate-700">
                <input type="checkbox" wire:model="application_verified" class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span>Sistem/aplikasi sudah sesuai</span>
            </label>
            @error('application_verified')
                <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2 border-t border-slate-100 pt-5">
            <label for="note" class="block text-xs font-bold text-slate-700">Catatan Revisi / Penolakan <span class="font-normal text-slate-400">(wajib untuk Minta Revisi atau Tolak)</span></label>
            <textarea id="note" wire:model="note" rows="3" maxlength="1000"
                placeholder="Tuliskan alasan pengembalian revisi atau penolakan..."
                class="w-full rounded-xl border border-slate-200 p-3 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            @error('note')
                <span class="text-xs font-medium text-rose-600">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('change-requests.index', ['queue' => 'incoming']) }}"
                class="inline-flex items-center justify-center rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">
                Batal
            </a>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <button type="button" wire:click="rejectRequest" wire:loading.attr="disabled"
                    wire:target="createTicket,requestRevision,rejectRequest"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60">
                    Tolak
                </button>
                <button type="button" wire:click="requestRevision" wire:loading.attr="disabled"
                    wire:target="createTicket,requestRevision,rejectRequest"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60">
                    Minta Revisi
                </button>
                <button type="submit" wire:loading.attr="disabled"
                    wire:target="createTicket,requestRevision,rejectRequest"
                    class="rounded-xl bg-blue-900 px-5 py-2.5 text-xs font-semibold text-white shadow transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="createTicket">Buat Tiket dan Kirim ke Change Manager</span>
                    <span wire:loading wire:target="createTicket">Memproses...</span>
                </button>
            </div>
        </div>
    </form>
</div>
