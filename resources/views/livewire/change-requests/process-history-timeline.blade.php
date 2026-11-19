<x-dashboard.panel title="Riwayat Proses" description="Kronologi proses dan langkah berikutnya."
    wire:poll.30s.visible="refreshProcessHistory"
    aria-label="Riwayat proses, diperbarui otomatis setiap 30 detik">
    <x-slot:icon>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
    </x-slot:icon>

    <x-slot:actions>
        <span title="Diperbarui otomatis setiap 30 detik"
            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">
            <span class="relative flex h-1.5 w-1.5" aria-hidden="true">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-70"></span>
                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            </span>
            Otomatis
        </span>
    </x-slot:actions>

    @php
        $circleTone = fn (string $tone): string => match ($tone) {
            'success' => 'bg-emerald-500 text-white ring-emerald-100',
            'danger' => 'bg-rose-500 text-white ring-rose-100',
            'warning' => 'bg-amber-500 text-white ring-amber-100',
            'info' => 'bg-blue-500 text-white ring-blue-100',
            default => 'bg-slate-300 text-white ring-slate-100',
        };

        // Warna penanda hanya untuk data berlevel agar baris lain tetap tenang.
        $detailChipTone = function (?string $label, string $value): ?string {
            $normalizedLabel = mb_strtolower(trim((string) $label));
            $normalizedValue = mb_strtolower(trim($value));

            $levelTone = match (true) {
                in_array($normalizedValue, ['rendah', 'low'], true) => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                in_array($normalizedValue, ['sedang', 'menengah', 'medium'], true) => 'border-amber-200 bg-amber-50 text-amber-700',
                in_array($normalizedValue, ['tinggi', 'kritis', 'darurat', 'high', 'urgent'], true) => 'border-rose-200 bg-rose-50 text-rose-700',
                default => null,
            };

            if (in_array($normalizedLabel, ['risiko', 'dampak', 'tingkat risiko', 'tingkat dampak', 'prioritas'], true)) {
                return $levelTone ?? 'border-slate-200 bg-white text-slate-600';
            }

            if (in_array($normalizedLabel, ['klasifikasi', 'klasifikasi perubahan'], true)) {
                return 'border-blue-200 bg-blue-50 text-blue-700';
            }

            return null;
        };

        $eventsByDay = collect($events)->groupBy(
            fn (array $event): string => $event['time']?->toDateString() ?? 'tanpa-tanggal'
        );
    @endphp

    <div class="p-5 sm:p-6">
        @if ($eventsByDay->isEmpty())
            <div class="flex items-start gap-3 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-4">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-white text-slate-400 ring-1 ring-slate-200">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-600">Belum ada riwayat proses</p>
                    <p class="mt-0.5 text-[11px] leading-relaxed text-slate-400">
                        Kejadian akan muncul di sini setelah RFC mulai diproses.
                    </p>
                </div>
            </div>
        @else
            <div class="space-y-6">
                @foreach ($eventsByDay as $day => $dayEvents)
                    <div wire:key="process-history-day-{{ $day }}">
                        <div class="mb-3 flex items-center gap-3">
                            <span class="h-px flex-1 bg-gradient-to-r from-transparent to-slate-200" aria-hidden="true"></span>
                            <p class="shrink-0 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                {{ $dayEvents->first()['time']?->copy()->locale('id')->translatedFormat('l, d M Y') ?? 'Tanpa tanggal' }}
                            </p>
                            <span class="h-px flex-1 bg-gradient-to-l from-transparent to-slate-200" aria-hidden="true"></span>
                        </div>

                        <ol
                            class="relative space-y-4 before:pointer-events-none before:absolute before:bottom-3 before:left-[13px] before:top-3 before:w-px before:bg-slate-200 before:content-['']">
                            @foreach ($dayEvents as $event)
                                @php
                                    $tone = $event['tone'] ?? 'neutral';
                                    $details = $event['details'] ?? [];
                                    $hasLongDetail = collect($details)->contains(
                                        fn (array $detail): bool => ($detail['long'] ?? false) === true
                                    );
                                    $actorName = filled($event['actor_name'] ?? null) ? trim((string) $event['actor_name']) : null;
                                    $actorRole = filled($event['actor_role'] ?? null) ? trim((string) $event['actor_role']) : null;
                                    $initials = $actorName !== null
                                        ? collect(preg_split('/\s+/u', $actorName))
                                            ->filter()
                                            ->take(2)
                                            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                                            ->implode('')
                                        : '';
                                    // Peran yang sudah tersirat pada judul tidak diulang
                                    // agar baris pelaku tetap pendek di kolom sempit.
                                    // Peran Admin/Super Admin tidak ditampilkan karena sudah
                                    // tidak ada pada desain alur baru.
                                    $showActorRole = $actorRole !== null
                                        && ! in_array(mb_strtolower($actorRole), ['admin', 'super admin'], true)
                                        && mb_strtolower($actorRole) !== mb_strtolower((string) $actorName)
                                        && ! str_contains(mb_strtolower((string) $event['label']), mb_strtolower($actorRole));
                                @endphp

                                <li wire:key="process-history-event-{{ $day }}-{{ $loop->index }}-{{ $event['time']?->timestamp ?? 'na' }}"
                                    class="relative flex items-start gap-3">
                                    <span
                                        class="relative z-10 grid h-7 w-7 shrink-0 place-items-center rounded-full shadow-sm ring-4 ring-white {{ $circleTone($tone) }}">
                                        @if ($tone === 'success')
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                        @elseif ($tone === 'danger')
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                    d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                        @elseif ($tone === 'warning')
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"
                                                    d="M12 9v4m0 4h.01" />
                                            </svg>
                                        @else
                                            <span class="h-2 w-2 rounded-full bg-white"></span>
                                        @endif
                                    </span>

                                    <div class="min-w-0 flex-1 pt-0.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <h3 class="min-w-0 text-[13px] font-semibold leading-snug text-slate-800 [text-wrap:balance]">
                                                {{ $event['label'] }}
                                            </h3>

                                            @if ($event['time'])
                                                <time datetime="{{ $event['time']->toIso8601String() }}"
                                                    title="{{ $event['time']->copy()->locale('id')->translatedFormat('d M Y, H:i') }}"
                                                    class="mt-0.5 shrink-0 whitespace-nowrap text-[11px] tabular-nums text-slate-400">
                                                    {{ $event['time']->format('H:i') }}
                                                </time>
                                            @endif
                                        </div>

                                        @if ($actorName !== null)
                                            <div class="mt-1.5 flex items-center gap-2">
                                                <span
                                                    class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-slate-100 text-[9px] font-bold text-slate-500 ring-1 ring-slate-200"
                                                    aria-hidden="true">{{ $initials }}</span>
                                                <p class="min-w-0 truncate text-[11px] text-slate-500">
                                                    <span class="text-slate-400">Oleh</span>
                                                    <span class="font-medium text-slate-600">{{ $actorName }}</span>
                                                </p>
                                                @if ($showActorRole)
                                                    <span
                                                        class="shrink-0 rounded-full border border-slate-200 bg-white px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-slate-500">{{ $actorRole }}</span>
                                                @endif
                                            </div>
                                        @endif

                                        @if ($details !== [])
                                            <div class="mt-2 rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2.5"
                                                @if ($hasLongDetail) x-data="{ open: false }" @endif>
                                                <ul class="space-y-1.5">
                                                    @foreach ($details as $detail)
                                                        @php
                                                            $chipTone = $detailChipTone($detail['label'] ?? null, $detail['value']);
                                                        @endphp
                                                        <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                                            @if (filled($detail['label']))
                                                                <span
                                                                    class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $detail['label'] }}</span>
                                                            @endif

                                                            @if ($chipTone !== null)
                                                                <span
                                                                    class="shrink-0 rounded-md border px-1.5 py-0.5 text-[11px] font-semibold {{ $chipTone }}">{{ $detail['value'] }}</span>
                                                            @else
                                                                <span
                                                                    class="min-w-0 flex-1 text-xs leading-relaxed {{ filled($detail['label']) ? 'text-slate-700' : 'text-slate-600' }}"
                                                                    @if ($detail['long'] ?? false) x-bind:class="open ? '' : 'line-clamp-3'" @endif>{{ $detail['value'] }}</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>

                                                @if ($hasLongDetail)
                                                    <button type="button" @click="open = !open"
                                                        class="mt-1.5 inline-flex items-center gap-1 rounded text-[11px] font-semibold text-blue-700 transition hover:text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                                                        <span x-text="open ? 'Sembunyikan' : 'Selengkapnya'">Selengkapnya</span>
                                                    </button>
                                                @endif
                                            </div>
                                        @endif

                                        @if (filled($event['attachment_url'] ?? null))
                                            <button type="button"
                                                data-viewer-url="{{ $event['attachment_url'] }}"
                                                data-viewer-title="{{ $event['attachment_name'] ?? 'Lampiran' }}"
                                                @click="$dispatch('open-attachment-viewer', { url: $el.dataset.viewerUrl, title: $el.dataset.viewerTitle })"
                                                class="mt-2 inline-flex items-center gap-1.5 rounded text-[11px] font-semibold text-blue-700 transition hover:text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z" />
                                                </svg>
                                                Lihat lampiran
                                            </button>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
            </div>
        @endif

        @if (count($nextSteps) > 0)
            <div class="mt-6 border-t border-slate-100 pt-4">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Langkah Berikutnya
                    </p>
                    <span
                        class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">{{ count($nextSteps) }}
                        tahap</span>
                </div>

                <ol
                    class="relative space-y-2.5 before:pointer-events-none before:absolute before:bottom-2 before:left-[13px] before:top-2 before:border-l before:border-dashed before:border-slate-200 before:content-['']">
                    @foreach ($nextSteps as $step)
                        <li wire:key="process-history-next-{{ $loop->index }}"
                            class="relative flex items-center gap-3">
                            <span
                                class="relative z-10 grid h-7 w-7 shrink-0 place-items-center rounded-full ring-4 ring-white {{ $loop->first ? 'bg-blue-600 text-white shadow-sm' : 'border border-slate-200 bg-white' }}">
                                @if ($loop->first)
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"
                                            d="M13.5 4.5 21 12l-7.5 7.5M21 12H3" />
                                    </svg>
                                @else
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                                @endif
                            </span>

                            <p class="min-w-0 text-xs {{ $loop->first ? 'font-semibold text-slate-700' : 'font-medium text-slate-400' }}">
                                {{ $step['label'] }}
                            </p>

                            @if ($loop->first)
                                <span
                                    class="ml-auto shrink-0 rounded-full border border-blue-200 bg-blue-50 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-blue-700">Berikutnya</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </div>
</x-dashboard.panel>
