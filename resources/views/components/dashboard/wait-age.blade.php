@props(['days' => 0, 'since' => null])

@php
    $days = max(0, (int) $days);
    $tone = match (true) {
        $days >= 7 => 'bg-rose-50 text-rose-700 ring-rose-100',
        $days >= 3 => 'bg-amber-50 text-amber-700 ring-amber-100',
        default => 'bg-slate-100 text-slate-600 ring-slate-200',
    };

    $sinceLabel = filled($since)
        ? \Illuminate\Support\Carbon::parse($since)
            ->timezone(config('app.timezone'))
            ->locale('id')
            ->translatedFormat('d F Y, H:i') . ' WIB'
        : null;
@endphp

<span
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold tabular-nums ring-1 ' . $tone]) }}
    aria-label="Usia tunggu {{ number_format($days, 0, ',', '.') }} hari{{ $sinceLabel ? ', sejak ' . $sinceLabel : '' }}"
    @if ($sinceLabel) title="Sejak {{ $sinceLabel }}" @endif>
    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9"
            d="M12 8.25v3.75l2.25 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
    {{ number_format($days, 0, ',', '.') }} hari
</span>
