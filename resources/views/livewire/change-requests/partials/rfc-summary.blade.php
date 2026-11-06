@php
    $status = \App\Enums\RfcStatus::fromValue($rfc->status);
    $opdName = $rfc->submittedBy?->opd?->name ?? $rfc->application?->opd?->name;
    $priority = $rfc->priority instanceof \App\Enums\TicketPriority
        ? $rfc->priority
        : \App\Enums\TicketPriority::tryFrom((string) $rfc->priority);
    $serviceCategory = $rfc->category instanceof \App\Enums\ServiceCategory
        ? $rfc->category
        : \App\Enums\ServiceCategory::tryFrom((string) $rfc->category);
    $hasTicketInformation = filled($rfc->ticket_number)
        || filled($priority)
        || filled($rfc->priority)
        || filled($serviceCategory)
        || filled($rfc->category)
        || filled($rfc->admin_note);
    $attachments = $rfc->relationLoaded('attachments') ? $rfc->attachments : $rfc->attachments()->get();
@endphp

<div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <span class="rounded-md bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-900">{{ $rfc->displayIdentifier() }}</span>
            <h2 class="mt-2 text-lg font-bold text-slate-800">{{ $rfc->title }}</h2>
        </div>
        <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
    </div>

    <div class="grid grid-cols-1 gap-4 text-xs sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-slate-400">Pemohon</p>
            <p class="mt-0.5 font-semibold text-slate-800">{{ $rfc->submittedBy?->name ?? 'Belum diisi' }}</p>
        </div>
        <div>
            <p class="text-slate-400">OPD / Instansi</p>
            <p class="mt-0.5 font-semibold text-slate-800">{{ filled($opdName) ? $opdName : 'Belum diisi' }}</p>
        </div>
        <div>
            <p class="text-slate-400">Aplikasi / Layanan</p>
            <p class="mt-0.5 font-semibold text-slate-800">{{ $rfc->application?->name ?? 'Belum diisi' }}</p>
        </div>
        <div>
            <p class="text-slate-400">Kategori Perubahan</p>
            <div class="mt-0.5">
                <x-category-badge :kategori="$rfc->change_type" size="sm" />
            </div>
        </div>
        <div>
            <p class="text-slate-400">Tanggal Pengajuan</p>
            <p class="mt-0.5 font-semibold text-slate-800">
                {{ $rfc->created_at?->copy()->timezone(config('app.timezone'))->format('d M Y, H:i') ?? 'Belum diisi' }} WIB
            </p>
        </div>
        @if (filled($rfc->target_implementation_date))
            <div>
                <p class="text-slate-400">Target Implementasi</p>
                <p class="mt-0.5 font-semibold text-slate-800">
                    {{ \Illuminate\Support\Carbon::parse($rfc->target_implementation_date)->timezone(config('app.timezone'))->format('d M Y, H:i').' WIB' }}
                </p>
            </div>
        @endif
        <div>
            <p class="text-slate-400">Persetujuan Kepala Dinas</p>
            <p class="mt-0.5 font-semibold text-slate-800">
                @if ($rfc->head_of_department_approved)
                    Disetujui
                    @if ($rfc->head_of_department_approved_at)
                        <span class="block font-normal text-slate-600">
                            {{ $rfc->head_of_department_approved_at->copy()->timezone(config('app.timezone'))->format('d M Y, H:i') }} WIB
                        </span>
                    @endif
                @else
                    Belum ada pernyataan persetujuan
                @endif
            </p>
        </div>
        @if ($rfc->is_physical_letter)
            <div>
                <p class="text-slate-400">Nota Dinas</p>
                <p class="mt-0.5 font-semibold text-slate-800">Ya</p>
            </div>
            @if (filled($rfc->physical_letter_number))
                <div>
                    <p class="text-slate-400">Nomor Surat</p>
                    <p class="mt-0.5 font-semibold text-slate-800">{{ $rfc->physical_letter_number }}</p>
                </div>
            @endif
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 border-t border-slate-100 pt-4 text-xs sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <p class="font-semibold text-slate-500">Detail Perubahan</p>
            <p class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-700">{{ filled($rfc->description) ? $rfc->description : 'Belum diisi' }}</p>
        </div>
        <div>
            <p class="font-semibold text-slate-500">Alasan Perubahan</p>
            <p class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-700">{{ filled($rfc->reason_for_change) ? $rfc->reason_for_change : 'Belum diisi' }}</p>
        </div>
        <div>
            <p class="font-semibold text-slate-500">Dasar Hukum</p>
            <p class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-700">{{ filled($rfc->legal_basis) ? $rfc->legal_basis : 'Belum diisi' }}</p>
        </div>
        <div>
            <p class="font-semibold text-slate-500">Risiko Perubahan</p>
            <p class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-700">{{ filled($rfc->change_risk) ? $rfc->change_risk : 'Belum diisi' }}</p>
        </div>
        <div>
            <p class="font-semibold text-slate-500">Keterangan</p>
            <p class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-700">{{ filled($rfc->impact_description) ? $rfc->impact_description : 'Belum diisi' }}</p>
        </div>
    </div>

    @if ($hasTicketInformation)
        <div class="space-y-3 border-t border-slate-100 pt-4">
            <h3 class="text-sm font-bold text-slate-800">Informasi Tiket dari Admin</h3>
            <div class="grid grid-cols-1 gap-4 text-xs md:grid-cols-3">
                @if (filled($rfc->ticket_number))
                    <div>
                        <p class="text-slate-400">Nomor Tiket</p>
                        <p class="mt-0.5 font-semibold text-slate-800">{{ $rfc->ticket_number }}</p>
                    </div>
                @endif
                @if (filled($priority) || filled($rfc->priority))
                    <div>
                        <p class="text-slate-400">Prioritas</p>
                        <p class="mt-0.5 font-semibold text-slate-800">{{ $priority?->label() ?? $rfc->priority }}</p>
                    </div>
                @endif
                @if (filled($serviceCategory) || filled($rfc->category))
                    <div>
                        <p class="text-slate-400">Kategori Layanan</p>
                        <p class="mt-0.5 font-semibold text-slate-800">{{ $serviceCategory?->label() ?? $rfc->category }}</p>
                    </div>
                @endif
            </div>
            @if (filled($rfc->admin_note))
                <div class="text-xs">
                    <p class="font-semibold text-slate-500">Catatan Admin untuk Change Manager</p>
                    <p class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-700">{{ $rfc->admin_note }}</p>
                </div>
            @endif
        </div>
    @endif

    <div class="border-t border-slate-100 pt-4">
        <h3 class="text-sm font-bold text-slate-800">Lampiran</h3>
        @if ($attachments->isEmpty())
            <p class="mt-2 text-xs text-slate-500">Belum ada lampiran.</p>
        @else
            <ul class="mt-2 divide-y divide-slate-100">
                @foreach ($attachments as $attachment)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-2.5 text-xs">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-700">{{ $attachment->original_name }}</p>
                            <p class="text-slate-400">
                                {{ $attachment->isApprovalLetter() ? 'Surat Persetujuan' : 'Lampiran Pendukung' }}
                                @if ($attachment->size)
                                    &middot; {{ number_format($attachment->size / 1024, 0, ',', '.') }} KB
                                @endif
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]) }}"
                                target="_blank" rel="noopener"
                                class="rounded-lg bg-slate-100 px-3 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-200">
                                Lihat
                            </a>
                            <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment, 'download' => 1]) }}"
                                class="rounded-lg bg-blue-600 px-3 py-1.5 font-semibold text-white transition hover:bg-blue-700">
                                Unduh
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
