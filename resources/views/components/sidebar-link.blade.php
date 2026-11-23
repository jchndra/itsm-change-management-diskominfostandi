@props([
    'href',
    'active' => false,
    'badge' => null,
    'nested' => false,
])

@php
    $label = trim(strip_tags((string) $slot));
@endphp

<a href="{{ $href }}" wire:navigate x-on:click="sidebarOpen = false"
    :title="sidebarCompact ? '{{ $label }}' : ''"
    :aria-label="sidebarCompact ? '{{ $label }}' : null"
    @class([
        'group flex items-center gap-3 rounded-xl font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#08294a]',
        'px-3 py-2.5 text-[13px]' => $nested,
        'px-3.5 py-3 text-sm' => ! $nested,
        'bg-[#0d6efd] text-white shadow-lg shadow-blue-950/30' => $active,
        'text-blue-50/80 hover:bg-white/[0.08] hover:text-white' => ! $active,
    ])
    @if ($active) aria-current="page" @endif>
    <span @class([
        'grid h-5 w-5 shrink-0 place-items-center',
        'text-white' => $active,
        'text-blue-100/70 group-hover:text-white' => ! $active,
    ])>
        {{ $icon }}
    </span>

    <span class="flex-1 truncate" x-show="!sidebarCompact">{{ $slot }}</span>

    @if (! is_null($badge))
        <span data-notification-badge x-show="!sidebarCompact"
            class="grid h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1.5 text-[10px] font-bold leading-none text-white">{{ $badge }}</span>
    @endif
</a>
