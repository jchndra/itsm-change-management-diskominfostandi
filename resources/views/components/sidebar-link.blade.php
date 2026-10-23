@props([
    'href',
    'active' => false,
    'badge' => null,
])

<a href="{{ $href }}" wire:navigate
    @class([
        'group flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-colors',
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

    <span class="flex-1 truncate">{{ $slot }}</span>

    @if (! is_null($badge))
        <span data-notification-badge
            class="grid h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1.5 text-[10px] font-bold leading-none text-white">{{ $badge }}</span>
    @endif
</a>
