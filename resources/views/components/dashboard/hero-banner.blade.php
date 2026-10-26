@props([
    'title',
    'description',
    'userName',
    'dateTime',
    'roleLabel',
])

@php
    $gridId = 'hero-grid-' . md5($title);
@endphp

<section
    {{ $attributes->merge(['class' => 'relative isolate overflow-hidden rounded-2xl border border-blue-100 bg-[#edf5ff] shadow-sm']) }}>
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute -left-20 -top-28 h-72 w-72 rounded-full bg-blue-200/35 blur-3xl"></div>
        <div class="absolute -bottom-32 right-24 h-72 w-72 rounded-full bg-sky-200/40 blur-3xl"></div>

        <svg class="absolute inset-0 h-full w-full text-blue-200/70">
            <defs>
                <pattern id="{{ $gridId }}" width="32" height="32" patternUnits="userSpaceOnUse">
                    <path d="M32 0H0V32" fill="none" stroke="currentColor" stroke-width="1" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#{{ $gridId }})" opacity="0.28" />
        </svg>
    </div>

    <span class="absolute inset-y-6 left-0 w-1 rounded-r-full bg-amber-400" aria-hidden="true"></span>

    <div
        class="relative z-10 grid min-h-[214px] gap-6 px-6 py-6 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(280px,380px)] lg:items-center lg:py-5">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-2">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-blue-200/80 bg-white/80 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-blue-700 shadow-sm">
                    <span class="h-1 w-5 rounded-full bg-amber-400" aria-hidden="true"></span>
                    {{ $roleLabel }}
                </span>
                <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-blue-500/80">
                    Diskominfostandi Kota Bekasi
                </span>
            </div>

            <p class="mt-4 text-sm font-medium text-slate-600">Selamat datang, {{ $userName }}</p>

            <h1 class="mt-1 text-[28px] font-bold leading-tight tracking-tight text-[#0b2e59] sm:text-[34px]">
                {{ $title }}
            </h1>

            <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600">
                {{ $description }}
            </p>

            <div class="mt-5 flex flex-wrap items-center gap-2.5">
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-blue-100 bg-white/80 px-3 py-2 text-xs font-medium text-slate-600">
                    <svg class="h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 5.25h13.5A1.5 1.5 0 0 1 20.25 6.75v12A1.5 1.5 0 0 1 18.75 20.25H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" />
                    </svg>
                    {{ $dateTime }} WIB
                </span>

                <button type="button" wire:click="$refresh" wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0b3b6f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#123f73] focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200 disabled:cursor-wait disabled:opacity-70">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true" wire:loading.remove wire:target="$refresh">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v6h6M20 20v-6h-6M20 9A8 8 0 0 0 5.3 5.3L4 10m16 4-1.3 4.7A8 8 0 0 1 4 15" />
                    </svg>
                    <svg class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true" wire:loading wire:target="$refresh">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 3a9 9 0 1 0 9 9" />
                    </svg>
                    <span wire:loading.remove wire:target="$refresh">Muat ulang</span>
                    <span wire:loading wire:target="$refresh">Memuat...</span>
                </button>
            </div>
        </div>

        <div class="relative hidden min-h-[204px] items-center justify-center lg:flex">
            <span class="absolute inset-y-6 left-0 w-px bg-gradient-to-b from-transparent via-blue-200 to-transparent"
                aria-hidden="true"></span>
            <span class="absolute inset-x-8 top-1/2 h-40 -translate-y-1/2 rounded-full bg-white/55 blur-2xl"
                aria-hidden="true"></span>

            <x-dashboard.hero-illustration class="relative z-10 w-full max-w-[390px]" />

            <span
                class="absolute bottom-0 right-2 inline-flex items-center gap-1.5 rounded-full border border-blue-100 bg-white/85 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-blue-700 shadow-sm"
                aria-hidden="true">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                Kota Bekasi
            </span>
        </div>
    </div>
</section>
