@props([
    'id',
    'label',
    'active' => false,
])

<div class="space-y-1" x-data="{ open: {{ $active ? 'true' : 'false' }} }">
    <button type="button"
        x-on:click="if (sidebarCompact) { $dispatch('sidebar-expand'); open = true; } else { open = !open; }"
        x-bind:aria-expanded="(open && ! sidebarCompact).toString()" aria-expanded="{{ $active ? 'true' : 'false' }}"
        aria-controls="sidebar-submenu-{{ $id }}"
        :title="sidebarCompact ? '{{ $label }}' : ''"
        :aria-label="sidebarCompact ? '{{ $label }}: buka submenu' : null"
        @class([
            'group flex w-full items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#08294a]',
            'bg-white/[0.06] text-white' => $active,
            'text-blue-50/80 hover:bg-white/[0.08] hover:text-white' => ! $active,
        ])>
        <span @class([
            'grid h-5 w-5 shrink-0 place-items-center',
            'text-white' => $active,
            'text-blue-100/70 group-hover:text-white' => ! $active,
        ]) aria-hidden="true">
            {{ $icon }}
        </span>

        <span class="flex-1 truncate text-left" x-show="!sidebarCompact">{{ $label }}</span>

        <svg x-show="!sidebarCompact" aria-hidden="true"
            class="h-4 w-4 shrink-0 text-blue-200/70 transition-transform duration-200 motion-reduce:transition-none"
            :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
        </svg>
    </button>

    <div id="sidebar-submenu-{{ $id }}" x-show="open && !sidebarCompact" x-cloak
        x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
        x-transition:enter-start="opacity-0 -translate-y-1 motion-reduce:translate-y-0"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1 motion-reduce:translate-y-0"
        class="ml-3.5 space-y-1 border-l border-white/10 pl-2.5">
        {{ $slot }}
    </div>
</div>
