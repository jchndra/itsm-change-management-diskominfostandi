@php
    $evidences = $evidences ?? collect();
@endphp

<div class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm md:grid-cols-2">
    <div>
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Hasil</span>
        <p class="font-medium text-slate-800">
            {{ \App\Enums\ImplementationResult::labelFor($implementation->implementation_result) }}
        </p>
    </div>
    <div>
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Pelaksana Perubahan</span>
        <p class="font-medium text-slate-800">{{ $implementation->executor_name }}</p>
    </div>
    <div>
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Waktu Mulai</span>
        <p class="font-medium text-slate-800">
            {{ $implementation->started_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
        </p>
    </div>
    <div>
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Waktu Selesai</span>
        <p class="font-medium text-slate-800">
            {{ $implementation->finished_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
        </p>
    </div>
    <div class="md:col-span-2">
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Sumber Laporan</span>
        <p class="font-medium text-slate-800">{{ $implementation->report_source ?? '-' }}</p>
    </div>
    <div class="md:col-span-2">
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Ringkasan Hasil</span>
        <p class="whitespace-pre-wrap text-slate-800">{{ $implementation->summary }}</p>
    </div>
    <div class="md:col-span-2">
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Hasil Pengujian</span>
        <p class="whitespace-pre-wrap text-slate-800">{{ $implementation->test_results }}</p>
    </div>
    @if (filled($implementation->failure_notes))
        <div class="md:col-span-2">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Catatan Kegagalan / Rollback</span>
            <p class="whitespace-pre-wrap text-slate-800">{{ $implementation->failure_notes }}</p>
        </div>
    @endif
    <div>
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Tindak Lanjut</span>
        <p class="font-medium text-slate-800">
            {{ filled($implementation->follow_up_action) ? \App\Enums\FollowUpAction::labelFor($implementation->follow_up_action) : '-' }}
        </p>
    </div>
    @if (filled($implementation->follow_up_notes))
        <div>
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Keterangan Tindak Lanjut</span>
            <p class="whitespace-pre-wrap text-slate-800">{{ $implementation->follow_up_notes }}</p>
        </div>
    @endif
</div>

<div class="mt-6 border-t border-slate-100 pt-4">
    <h4 class="mb-3 text-sm font-bold text-slate-700">
        Bukti Pendukung ({{ $evidences->count() }} file)
    </h4>

    @if ($evidences->isEmpty())
        <p class="text-sm text-slate-500">Tidak ada berkas bukti.</p>
    @else
        <ul class="divide-y divide-slate-100">
            @foreach ($evidences as $evidence)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $evidence->original_filename }}</p>
                        <p class="text-xs text-slate-400">
                            {{ number_format($evidence->file_size / 1024, 0, ',', '.') }} KB
                            &middot; {{ $evidence->mime_type }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
