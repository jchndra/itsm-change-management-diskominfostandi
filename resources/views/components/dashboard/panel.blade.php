@props(['title', 'description' => null, 'id' => null])

@php
    $headingId = $id ?: 'panel-' . md5($title);
@endphp

<section
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm']) }}
    aria-labelledby="{{ $headingId }}">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div class="flex min-w-0 items-start gap-3">
            @isset($icon)
                <span
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600 ring-1 ring-blue-100">
                    {{ $icon }}
                </span>
            @endisset

            <div class="min-w-0">
                <h2 id="{{ $headingId }}" class="text-base font-semibold text-slate-800">{{ $title }}</h2>
                @if (filled($description))
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $description }}</p>
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
