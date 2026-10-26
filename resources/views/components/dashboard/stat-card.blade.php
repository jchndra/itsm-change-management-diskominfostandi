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
        'blue' => ['icon' => 'bg-blue-50 text-blue-600 ring-blue-100', 'accent' => 'bg-blue-500'],
        'indigo' => ['icon' => 'bg-indigo-50 text-indigo-600 ring-indigo-100', 'accent' => 'bg-indigo-500'],
        'amber' => ['icon' => 'bg-amber-50 text-amber-600 ring-amber-100', 'accent' => 'bg-amber-500'],
        'emerald' => ['icon' => 'bg-emerald-50 text-emerald-600 ring-emerald-100', 'accent' => 'bg-emerald-500'],
        'rose' => ['icon' => 'bg-rose-50 text-rose-600 ring-rose-100', 'accent' => 'bg-rose-500'],
        'slate' => ['icon' => 'bg-slate-100 text-slate-600 ring-slate-200', 'accent' => 'bg-slate-400'],
    ];
    $palette = $tones[$tone] ?? $tones['blue'];

    $changeValue = $change === null ? null : (float) $change;
    $isInverse = $comparisonPolarity === 'inverse';
    $isStable = $changeValue !== null && abs($changeValue) < 0.05;
    $isIncrease = $changeValue !== null && $changeValue > 0;
    $isFavourable = $isIncrease !== $isInverse;

    $comparisonTone = $isStable
        ? 'text-slate-500'
        : ($isFavourable ? 'text-emerald-600' : 'text-rose-600');
@endphp

<article
    {{ $attributes->merge(['class' => 'relative flex h-full min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow duration-200 hover:shadow-md']) }}>
    <span class="absolute inset-x-0 top-0 h-1 {{ $palette['accent'] }}" aria-hidden="true"></span>

    <div class="flex items-start justify-between gap-3">
        <p class="min-w-0 pt-1 text-[11px] font-bold uppercase leading-snug tracking-[0.12em] text-slate-500">
            {{ $label }}
        </p>

        @isset($icon)
            <span
                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl ring-1 {{ $palette['icon'] }}">
                {{ $icon }}
            </span>
        @endisset
    </div>

    <p class="mt-4 text-[34px] font-bold leading-none tabular-nums tracking-tight text-slate-900">
        {{ number_format((int) $value, 0, ',', '.') }}
    </p>

    @if (filled($hint))
        <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ $hint }}</p>
    @endif

    @if ($showComparison)
        <div class="mt-auto pt-4">
            @if ($changeValue === null)
                <p class="text-[11px] font-medium leading-relaxed text-slate-500">Belum ada data pembanding</p>
            @else
                <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[11px] leading-relaxed text-slate-500">
                    <span class="inline-flex items-center gap-1 font-bold {{ $comparisonTone }}">
                        @if ($isStable)
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                                    d="M5 12h14" />
                            </svg>
                        @elseif ($isIncrease)
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                                    d="M12 19V5m0 0-5 5m5-5 5 5" />
                            </svg>
                        @else
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                                    d="M12 5v14m0 0 5-5m-5 5-5-5" />
                            </svg>
                        @endif

                        <span>
                            {{ $isIncrease ? '+' : '' }}{{ number_format($changeValue, 1, ',', '.') }}%
                            <span class="sr-only">
                                {{ $isStable ? 'stabil' : ($isIncrease ? 'naik' : 'turun') }}
                            </span>
                        </span>
                    </span>
                    <span>dibanding 30 hari sebelumnya</span>
                </p>
            @endif
        </div>
    @endif
</article>
