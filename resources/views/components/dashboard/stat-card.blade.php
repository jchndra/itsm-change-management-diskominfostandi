@props([
    'label',
    'value' => 0,
    'hint' => null,
    'change' => null,
    'showComparison' => false,
    'comparisonPolarity' => 'normal',
    'tone' => 'blue',
])

@php
    $tones = [
        'blue' => [
            'icon' => 'from-blue-600 to-sky-500 shadow-blue-500/30',
            'wash' => 'from-blue-400/20',
            'bar' => 'from-blue-500 via-sky-400 to-cyan-300',
        ],
        'indigo' => [
            'icon' => 'from-indigo-600 to-violet-500 shadow-indigo-500/30',
            'wash' => 'from-indigo-400/20',
            'bar' => 'from-indigo-500 via-violet-400 to-fuchsia-300',
        ],
        'amber' => [
            'icon' => 'from-amber-500 to-orange-400 shadow-amber-500/30',
            'wash' => 'from-amber-400/20',
            'bar' => 'from-amber-500 via-orange-400 to-yellow-300',
        ],
        'emerald' => [
            'icon' => 'from-emerald-600 to-teal-500 shadow-emerald-500/30',
            'wash' => 'from-emerald-400/20',
            'bar' => 'from-emerald-500 via-teal-400 to-cyan-300',
        ],
        'rose' => [
            'icon' => 'from-rose-600 to-pink-500 shadow-rose-500/30',
            'wash' => 'from-rose-400/20',
            'bar' => 'from-rose-500 via-pink-400 to-orange-300',
        ],
        'slate' => [
            'icon' => 'from-slate-600 to-slate-400 shadow-slate-500/25',
            'wash' => 'from-slate-400/20',
            'bar' => 'from-slate-500 via-slate-400 to-slate-300',
        ],
    ];
    $palette = $tones[$tone] ?? $tones['blue'];

    $changeValue = $change === null ? null : (float) $change;
    $isInverse = $comparisonPolarity === 'inverse';
    $isStable = $changeValue !== null && abs($changeValue) < 0.05;
    $isIncrease = $changeValue !== null && $changeValue > 0;
    $isFavourable = $isIncrease !== $isInverse;

    $comparisonTone = $isStable
        ? 'bg-slate-100 text-slate-600 ring-slate-200'
        : ($isFavourable
            ? 'bg-emerald-50 text-emerald-700 ring-emerald-100'
            : 'bg-rose-50 text-rose-700 ring-rose-100');
@endphp

<article
    {{ $attributes->merge(['class' => 'group relative flex h-full min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04),0_18px_40px_-32px_rgba(15,23,42,0.45)] transition duration-300 hover:-translate-y-0.5 hover:border-slate-300/80 hover:shadow-[0_2px_4px_rgba(15,23,42,0.04),0_24px_50px_-30px_rgba(15,23,42,0.45)]']) }}>
    <span class="pointer-events-none absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r {{ $palette['bar'] }}"
        aria-hidden="true"></span>
    <span
        class="pointer-events-none absolute -right-10 -top-12 h-36 w-36 rounded-full bg-gradient-to-br {{ $palette['wash'] }} to-transparent blur-2xl"
        aria-hidden="true"></span>

    <div class="relative flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[10px] font-bold uppercase leading-snug tracking-[0.16em] text-slate-500">
                {{ $label }}
            </p>

            <p class="mt-3 text-[36px] font-bold leading-none tabular-nums tracking-[-0.04em] text-slate-900">
                {{ number_format((int) $value, 0, ',', '.') }}
            </p>
        </div>

        @isset($icon)
            <span
                class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br text-white shadow-lg ring-1 ring-white/20 {{ $palette['icon'] }}">
                {{ $icon }}
            </span>
        @endisset
    </div>

    @if (filled($hint))
        <p class="relative mt-2.5 text-xs leading-relaxed text-slate-500">{{ $hint }}</p>
    @endif

    @if ($showComparison)
        <div class="relative mt-auto pt-4">
            @if ($changeValue === null)
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-500 ring-1 ring-slate-200/80">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 12h14" />
                    </svg>
                    Belum ada data pembanding
                </span>
            @else
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold ring-1 {{ $comparisonTone }}">
                        @if ($isStable)
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"
                                    d="M5 12h14" />
                            </svg>
                        @elseif ($isIncrease)
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"
                                    d="M12 19V5m0 0-5 5m5-5 5 5" />
                            </svg>
                        @else
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"
                                    d="M12 5v14m0 0 5-5m-5 5-5-5" />
                            </svg>
                        @endif

                        {{ $isIncrease ? '+' : '' }}{{ number_format($changeValue, 1, ',', '.') }}%
                        <span class="sr-only">
                            {{ $isStable ? 'stabil' : ($isIncrease ? 'naik' : 'turun') }}
                        </span>
                    </span>
                    <span class="text-[10px] leading-relaxed text-slate-400">dibanding 30 hari sebelumnya</span>
                </div>
            @endif
        </div>
    @endif
</article>
