@props([
    'title',
    'description' => null,
    'eyebrow' => null,
    'iconTone' => 'blue',
])

@php
    $iconTones = [
        'blue' => 'bg-blue-600 shadow-blue-600/25',
        'navy' => 'bg-[#08294a] shadow-[#08294a]/25',
        'sky' => 'bg-sky-600 shadow-sky-600/25',
        'emerald' => 'bg-emerald-600 shadow-emerald-600/25',
        'amber' => 'bg-amber-500 shadow-amber-500/25',
        'rose' => 'bg-rose-600 shadow-rose-600/25',
        'violet' => 'bg-violet-600 shadow-violet-600/25',
        'slate' => 'bg-slate-700 shadow-slate-700/25',
    ];
@endphp

<div
    {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="flex min-w-0 items-start gap-3 sm:gap-4">
        @isset($icon)
            <span
                class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl text-white shadow-lg {{ $iconTones[$iconTone] ?? $iconTones['blue'] }}">
                {{ $icon }}
            </span>
        @endisset

        <div class="min-w-0">
            @if (filled($eyebrow))
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-blue-700">
                    {{ $eyebrow }}
                </p>
            @endif

            <h1
                class="text-[26px] font-bold leading-tight tracking-tight text-slate-900 sm:text-[30px] {{ filled($eyebrow) ? 'mt-1' : '' }}">
                {{ $title }}
            </h1>

            @if (filled($description))
                <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-500">
                    {{ $description }}
                </p>
            @endif

            @isset($meta)
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    {{ $meta }}
                </div>
            @endisset
        </div>
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</div>
