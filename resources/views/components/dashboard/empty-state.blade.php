@props(['title' => 'Belum ada data', 'description' => null])

<div
    {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-6 py-10 text-center']) }}>
    <span class="grid h-11 w-11 place-items-center rounded-full bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M4.5 19.5h15M6.75 16.5V9.75m5.25 6.75V6m5.25 10.5v-3" />
        </svg>
    </span>
    <p class="mt-3 text-sm font-semibold text-slate-700">{{ $title }}</p>
    @if (filled($description))
        <p class="mt-1 max-w-md text-xs leading-relaxed text-slate-500">{{ $description }}</p>
    @endif

    @isset($slot)
        <div class="mt-3">{{ $slot }}</div>
    @endisset
</div>
