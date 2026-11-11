<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6"
    wire:poll.30s.visible="refreshProcessHistory"
    aria-label="Riwayat proses, diperbarui otomatis setiap 30 detik">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Riwayat Proses
        </h3>

        <span title="Diperbarui otomatis setiap 30 detik"
            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">
            <span class="relative flex h-1.5 w-1.5" aria-hidden="true">
                <span
                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-70"></span>
                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            </span>
            Otomatis
        </span>
    </div>

    @if (empty($events))
        <p class="text-sm text-slate-500">Belum ada riwayat proses untuk RFC ini.</p>
    @else
        <div
            class="relative pl-6 space-y-6 before:absolute before:inset-0 before:ml-[11px] before:-translate-x-px before:h-full before:w-0.5 before:bg-slate-200">
            @foreach ($events as $event)
                @php
                    $tone = $event['tone'] ?? 'neutral';
                    $circle = match ($tone) {
                        'success' => 'bg-emerald-500 text-white ring-emerald-50',
                        'danger' => 'bg-rose-500 text-white ring-rose-50',
                        'warning' => 'bg-amber-500 text-white ring-amber-50',
                        'info' => 'bg-blue-500 text-white ring-blue-50',
                        default => 'bg-slate-300 text-white ring-slate-50',
                    };
                @endphp

                <div wire:key="process-history-event-{{ $loop->index }}-{{ $event['time']?->timestamp ?? 'na' }}"
                    class="relative flex items-start">
                    <div
                        class="absolute left-[-29px] flex items-center justify-center w-6 h-6 rounded-full {{ $circle }} shadow-sm ring-4 ring-white">
                        @if ($tone === 'success')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                        @elseif ($tone === 'danger')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        @elseif ($tone === 'warning')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"
                                    d="M12 9v4m0 4h.01"></path>
                            </svg>
                        @else
                            <span class="h-2 w-2 rounded-full bg-white"></span>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-start justify-between gap-1">
                            <h4 class="text-sm font-bold text-slate-800">{{ $event['label'] }}</h4>
                            @if ($event['time'])
                                <time class="text-[11px] text-slate-400 whitespace-nowrap">
                                    {{ $event['time']->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                                </time>
                            @endif
                        </div>

                        @if (filled($event['actor_name']))
                            <p class="text-[11px] text-slate-500 mt-1">
                                Oleh {{ $event['actor_name'] }}
                                @if (filled($event['actor_role']))
                                    &middot; {{ $event['actor_role'] }}
                                @endif
                            </p>
                        @endif

                        @if (filled($event['note']))
                            <p class="mt-1 text-xs text-slate-600 whitespace-pre-wrap">{{ $event['note'] }}</p>
                        @endif

                        @if (filled($event['attachment_url'] ?? null))
                            <a href="{{ $event['attachment_url'] }}" target="_blank" rel="noopener"
                                class="mt-1.5 inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Lihat lampiran
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if (count($nextSteps) > 0)
        <div class="mt-6 border-t border-slate-100 pt-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-3">Langkah Berikutnya</p>
            <div
                class="relative pl-6 space-y-4 before:absolute before:inset-0 before:ml-[11px] before:-translate-x-px before:h-full before:w-0.5 before:bg-slate-100">
                @foreach ($nextSteps as $step)
                    <div wire:key="process-history-next-{{ $loop->index }}" class="relative flex items-center">
                        <span
                            class="absolute left-[-29px] flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 ring-4 ring-white">
                            <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                        </span>
                        <p class="text-xs font-medium text-slate-400">{{ $step['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
