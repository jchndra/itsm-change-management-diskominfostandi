@props(['title', 'description' => null, 'id' => null])

@php
    $headingId = $id ?: 'panel-' . md5($title);
@endphp

<section
    {{ $attributes->merge(['class' => 'group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04),0_18px_40px_-32px_rgba(15,23,42,0.45)]']) }}
    aria-labelledby="{{ $headingId }}">
    <span class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-blue-300/70 to-transparent"
        aria-hidden="true"></span>

    <div
        class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100/90 bg-gradient-to-r from-slate-50/80 via-white to-white px-5 py-4">
        <div class="flex min-w-0 items-start gap-3">
            @isset($icon)
                <span
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-blue-600 to-sky-500 text-white shadow-md shadow-blue-500/25 ring-1 ring-white/20">
                    {{ $icon }}
                </span>
            @endisset

            <div class="min-w-0">
                <h2 id="{{ $headingId }}" class="text-[15px] font-bold tracking-tight text-slate-900">{{ $title }}
                </h2>
                @if (filled($description))
                    <p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-500">{{ $description }}</p>
                @endif
            </div>
        </div>

        @isset($actions)
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>

    {{ $slot }}
</section>
