@props([
    'prioritas' => null,
])

@php
    $rawValue = $prioritas instanceof \BackedEnum ? $prioritas->value : $prioritas;
    $normalizedValue = strtolower(trim((string) $rawValue));

    $level = match (true) {
        in_array($normalizedValue, ['low', 'rendah'], true) => 'low',
        in_array($normalizedValue, ['medium', 'sedang'], true) => 'medium',
        in_array($normalizedValue, ['high', 'tinggi'], true) => 'high',
        in_array($normalizedValue, ['urgent', 'darurat', 'mendesak'], true) => 'urgent',
        default => null,
    };

    [$label, $classes] = match ($level) {
        'low' => ['Rendah', 'border-slate-300 bg-slate-100 text-slate-700'],
        'medium' => ['Sedang', 'border-blue-300 bg-blue-50 text-blue-800'],
        'high' => ['Tinggi', 'border-amber-300 bg-amber-50 text-amber-800'],
        'urgent' => ['Darurat', 'border-rose-300 bg-rose-100 text-rose-800'],
        default => [trim((string) $rawValue), 'border-slate-300 bg-slate-50 text-slate-700'],
    };
@endphp

@if (filled($rawValue))
    <span
        {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-[11px] font-semibold {$classes}"]) }}
        data-priority="{{ $level ?? 'unknown' }}">
        <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
            aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 21V4m0 0h10l-2 4 2 4H5" />
        </svg>
        <span>Prioritas: {{ $label }}</span>
    </span>
@endif
