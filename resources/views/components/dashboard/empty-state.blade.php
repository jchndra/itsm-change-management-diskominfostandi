@props(['title' => 'Belum ada data', 'description' => null])

<div
    {{ $attributes->merge(['class' => 'relative flex flex-col items-center justify-center overflow-hidden rounded-2xl border border-dashed border-slate-200 bg-gradient-to-b from-slate-50/80 to-white px-6 py-10 text-center']) }}>
    <span class="pointer-events-none absolute inset-0 opacity-[0.35]" aria-hidden="true"
        style="background-image: radial-gradient(circle, rgb(148 163 184 / 0.35) 1px, transparent 1px); background-size: 18px 18px; mask-image: linear-gradient(to bottom, black, transparent 75%);"></span>

    <span
        class="relative grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br from-blue-50 to-sky-100 text-blue-500 ring-1 ring-blue-100 shadow-sm">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M4.5 19.5h15M6.75 16.5V9.75m5.25 6.75V6m5.25 10.5v-3" />
        </svg>
    </span>
    <p class="relative mt-3 text-sm font-semibold text-slate-700">{{ $title }}</p>
    @if (filled($description))
        <p class="relative mt-1 max-w-md text-xs leading-relaxed text-slate-500">{{ $description }}</p>
    @endif

    @isset($slot)
        <div class="relative mt-3">{{ $slot }}</div>
    @endisset
</div>
