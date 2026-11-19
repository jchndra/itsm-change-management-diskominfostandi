@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'navigate' => false,
    'type' => 'button',
])

@php
    $sizes = [
        'sm' => 'px-3 py-2 text-xs',
        'md' => 'px-4 py-2.5 text-sm',
    ];

    $variants = [
        'primary' => 'border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus-visible:ring-blue-600',
        'navy' => 'border border-transparent bg-[#08294a] text-white hover:bg-[#0b3767] focus-visible:ring-[#08294a]',
        'secondary' => 'border border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 focus-visible:ring-slate-400',
        'ghost' => 'border border-transparent bg-transparent text-slate-600 shadow-none hover:bg-slate-100 hover:text-slate-900 focus-visible:ring-slate-400',
        'success' => 'border border-transparent bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-500',
        'warning' => 'border border-transparent bg-amber-500 text-white hover:bg-amber-600 focus-visible:ring-amber-400',
        'danger' => 'border border-transparent bg-rose-600 text-white hover:bg-rose-700 focus-visible:ring-rose-500',
    ];

    $classes = collect([
        'inline-flex items-center justify-center gap-2 rounded-xl font-semibold shadow-sm transition',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-60',
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['primary'],
    ])->implode(' ');
@endphp

@if (filled($href))
    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif
        {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
