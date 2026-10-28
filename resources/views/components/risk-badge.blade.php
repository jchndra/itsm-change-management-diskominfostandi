@props([
    'risiko' => null,
])

@php
    $rawValue = $risiko instanceof \BackedEnum ? $risiko->value : $risiko;
    $normalizedValue = strtolower(trim((string) $rawValue));

    $level = match (true) {
        in_array($normalizedValue, ['low', 'rendah'], true) => 'low',
        in_array($normalizedValue, ['medium', 'sedang'], true) => 'medium',
        in_array($normalizedValue, ['high', 'tinggi'], true) => 'high',
        in_array($normalizedValue, ['critical', 'kritis'], true) => 'critical',
        default => null,
    };

    [$label, $classes] = match ($level) {
        'low' => ['Rendah', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        'medium' => ['Sedang', 'border-yellow-300 bg-yellow-50 text-yellow-800'],
        'high' => ['Tinggi', 'border-orange-300 bg-orange-50 text-orange-800'],
        'critical' => ['Kritis', 'border-red-300 bg-red-800 text-white'],
        default => [trim((string) $rawValue), 'border-slate-300 bg-slate-50 text-slate-700'],
    };
@endphp

@if (filled($rawValue))
    <span
        {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-lg border-l-4 px-2 py-1 text-[11px] font-semibold {$classes}"]) }}
        data-risk-level="{{ $level ?? 'unknown' }}">
        <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
            aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 14a8 8 0 1 1 16 0" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 14 4-4" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 17h.01" />
        </svg>
        <span>Risiko: {{ $label }}</span>
    </span>
@endif
