@props([
    'tone' => 'info',
    'title' => null,
    'dismiss' => null,
])

@php
    $tones = [
        'success' => [
            'wrapper' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'iconWrapper' => 'bg-emerald-100 text-emerald-600',
            'role' => 'status',
            'path' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        ],
        'info' => [
            'wrapper' => 'border-sky-200 bg-sky-50 text-sky-800',
            'iconWrapper' => 'bg-sky-100 text-sky-600',
            'role' => 'status',
            'path' => 'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM12 8.25h.008v.008H12V8.25Z',
        ],
        'warning' => [
            'wrapper' => 'border-amber-200 bg-amber-50 text-amber-800',
            'iconWrapper' => 'bg-amber-100 text-amber-600',
            'role' => 'alert',
            'path' => 'M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 3.94 2.7 17.06A1.9 1.9 0 0 0 4.34 20h15.32a1.9 1.9 0 0 0 1.64-2.9L13.66 3.94a1.9 1.9 0 0 0-3.32 0Z',
        ],
        'danger' => [
            'wrapper' => 'border-rose-200 bg-rose-50 text-rose-800',
            'iconWrapper' => 'bg-rose-100 text-rose-600',
            'role' => 'alert',
            'path' => 'M12 9v4m0 4h.01M10.34 3.94l-8.4 14.53A1.5 1.5 0 0 0 3.23 21h17.54a1.5 1.5 0 0 0 1.3-2.53l-8.4-14.53a1.5 1.5 0 0 0-2.6 0Z',
        ],
        'neutral' => [
            'wrapper' => 'border-slate-200 bg-slate-50 text-slate-700',
            'iconWrapper' => 'bg-slate-100 text-slate-500',
            'role' => 'status',
            'path' => 'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM12 8.25h.008v.008H12V8.25Z',
        ],
    ];

    $palette = $tones[$tone] ?? $tones['info'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border px-4 py-3 ' . $palette['wrapper']]) }}
    role="{{ $palette['role'] }}">
    <span
        class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full {{ $palette['iconWrapper'] }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $palette['path'] }}" />
        </svg>
    </span>

    <div class="min-w-0 flex-1 text-sm leading-relaxed">
        @if (filled($title))
            <p class="font-semibold">{{ $title }}</p>
        @endif

        <div class="min-w-0 {{ filled($title) ? 'mt-0.5 text-[13px]' : '' }}">
            {{ $slot }}
        </div>
    </div>

    @if (filled($dismiss))
        <button type="button" wire:click="{{ $dismiss }}"
            class="shrink-0 rounded-lg p-1 text-current opacity-60 transition hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-current focus-visible:ring-offset-2"
            aria-label="Tutup pemberitahuan">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
