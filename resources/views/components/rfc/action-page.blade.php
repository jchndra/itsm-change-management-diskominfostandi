@props([
    'backHref',
    'title',
    'description' => null,
    'eyebrow' => null,
    'backLabel' => 'Kembali',
    'width' => '4xl',
    'navigate' => true,
])

@php
    $widths = [
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
        '7xl' => 'max-w-7xl',
    ];

    $containerWidth = $widths[$width] ?? $widths['4xl'];
@endphp

<div {{ $attributes->merge(['class' => "mx-auto {$containerWidth} space-y-6"]) }}>
    <div class="flex flex-col gap-4 border-b border-slate-200/80 pb-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-start gap-3 sm:gap-4">
            <a href="{{ $backHref }}" @if ($navigate) wire:navigate @endif
                class="group grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2"
                aria-label="{{ $backLabel }}">
                <svg class="h-5 w-5 transition group-hover:-translate-x-0.5" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
            </a>

            <div class="min-w-0">
                @if (filled($eyebrow))
                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-blue-700">
                        {{ $eyebrow }}
                    </p>
                @endif
                <h1
                    class="text-2xl font-bold leading-tight tracking-tight text-slate-900 {{ filled($eyebrow) ? 'mt-1' : '' }}">
                    {{ $title }}
                </h1>
                @if (filled($description))
                    <p class="mt-1 text-sm leading-relaxed text-slate-500">
                        {{ $description }}
                    </p>
                @endif
            </div>
        </div>

        @isset($actions)
            <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                {{ $actions }}
            </div>
        @endisset
    </div>

    {{ $slot }}
</div>
