@props([
    'title',
    'description' => '',
    'userName',
    'dateTime' => null,
    'roleLabel',
    'showMeta' => true,
])

@php
    $gridId = 'hero-grid-' . md5($title);
@endphp

<section
    {{ $attributes->merge(['class' => 'relative isolate overflow-hidden rounded-3xl border border-[#103f6c] bg-[#08294a] shadow-[0_28px_70px_-38px_rgba(8,41,74,0.8)]']) }}>
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div
            class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(56,189,248,0.34),transparent_42%),radial-gradient(circle_at_bottom_left,rgba(245,158,11,0.18),transparent_38%)]">
        </div>
        <div class="absolute -left-24 top-10 h-72 w-72 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-32 right-20 h-80 w-80 rounded-full bg-sky-400/20 blur-3xl"></div>

        <svg class="absolute inset-0 h-full w-full text-blue-200/15">
            <defs>
                <pattern id="{{ $gridId }}" width="34" height="34" patternUnits="userSpaceOnUse">
                    <path d="M34 0H0V34" fill="none" stroke="currentColor" stroke-width="1" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#{{ $gridId }})" />
        </svg>

        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-blue-300/30 to-transparent">
        </div>
    </div>

    <span class="absolute inset-y-8 left-0 w-1 rounded-r-full bg-gradient-to-b from-amber-300 via-amber-400 to-transparent"
        aria-hidden="true"></span>

    <div
        class="{{ filled($description) || $showMeta ? 'min-h-[228px]' : 'min-h-0' }} relative z-10 grid gap-7 px-6 py-7 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(290px,390px)] lg:items-center lg:py-6">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-blue-50 shadow-sm backdrop-blur">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-300 shadow-[0_0_10px_rgba(252,211,77,0.8)]"
                        aria-hidden="true"></span>
                    {{ $roleLabel }}
                </span>
                <span class="text-[10px] font-semibold uppercase tracking-[0.16em] text-blue-200/70">
                    Diskominfostandi Kota Bekasi
                </span>
            </div>

            <p class="mt-5 text-sm font-medium text-blue-100/80">Selamat datang, {{ $userName }}</p>

            <h1
                class="mt-1.5 text-[30px] font-bold leading-[1.08] tracking-[-0.03em] text-white sm:text-[38px]">
                {{ $title }}
            </h1>

            @if (filled($description))
                <p class="mt-3 max-w-xl text-sm leading-relaxed text-blue-100/75">
                    {{ $description }}
                </p>
            @endif

            @if ($showMeta)
                <div class="mt-6 flex flex-wrap items-center gap-2.5">
                    @if (filled($dateTime))
                        <span
                            class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-xs font-medium text-blue-50 backdrop-blur">
                            <svg class="h-4 w-4 text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 5.25h13.5A1.5 1.5 0 0 1 20.25 6.75v12A1.5 1.5 0 0 1 18.75 20.25H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" />
                            </svg>
                            {{ $dateTime }} WIB
                        </span>
                    @endif

                    <button type="button" wire:click="$refresh" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#08294a] shadow-lg shadow-black/10 transition hover:-translate-y-0.5 hover:bg-blue-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-300/40 disabled:cursor-wait disabled:opacity-70">
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
            @endif
        </div>

        <div class="relative hidden min-h-[210px] items-center justify-center lg:flex">
            <span class="absolute inset-y-5 left-0 w-px bg-gradient-to-b from-transparent via-white/20 to-transparent"
                aria-hidden="true"></span>
            <span class="absolute inset-x-6 top-1/2 h-44 -translate-y-1/2 rounded-full bg-sky-300/15 blur-3xl"
                aria-hidden="true"></span>

            <div
                class="relative z-10 w-full max-w-[400px] rounded-2xl border border-white/10 bg-white/[0.07] p-2 shadow-2xl shadow-black/10 backdrop-blur-sm">
                <x-dashboard.hero-illustration class="h-auto w-full drop-shadow-[0_18px_30px_rgba(2,6,23,0.25)]" />
            </div>

            <span
                class="absolute bottom-0 right-1 inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-[#08294a]/80 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-blue-100 shadow-lg backdrop-blur"
                aria-hidden="true">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span>
                Kota Bekasi
            </span>
        </div>
    </div>
</section>
