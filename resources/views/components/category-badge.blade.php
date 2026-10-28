@props([
    'kategori' => null,
    'mode' => null,
    'size' => 'md',
    'label' => null,
])

@php
    $rawValue = $kategori instanceof \BackedEnum ? $kategori->value : $kategori;
    $normalizedValue = strtolower(trim((string) $rawValue));
    $normalizedValue = str_replace(['_', '-'], ' ', $normalizedValue);

    $category = match (true) {
        in_array($normalizedValue, ['standard', 'standar', 'standard change', 'perubahan standar'], true) => 'standard',
        in_array($normalizedValue, ['normal', 'normal change', 'perubahan normal'], true) => 'normal',
        in_array($normalizedValue, ['emergency', 'darurat', 'darurat (emergency)', 'emergency change', 'perubahan darurat'], true) => 'emergency',
        default => null,
    };

    [$defaultLabel, $classes, $icon] = match ($category) {
        'standard' => [
            'Standar',
            'border-slate-300 bg-slate-100 text-slate-700',
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
        ],
        'normal' => [
            'Normal',
            'border-blue-300 bg-blue-50 text-blue-800',
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M9 8h6M6 3h9l4 4v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />',
        ],
        'emergency' => [
            'Emergency',
            'border-red-300 bg-red-100 text-red-800',
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z" />',
        ],
        default => [
            'Belum dikategorikan',
            'border-dashed border-slate-300 bg-slate-50 text-slate-500',
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 9a2.25 2.25 0 1 1 3.6 1.83c-.65.45-1.35 1.02-1.35 1.92M12 17.25h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
        ],
    };

    $sizeClasses = $size === 'sm'
        ? 'gap-1 px-1.5 py-0.5 text-[10px]'
        : 'gap-1.5 px-2 py-1 text-[11px]';
    $displayLabel = filled($label) ? $label : $defaultLabel;
@endphp

<span
    {{ $attributes->merge(['class' => "inline-flex items-center rounded-md border font-semibold {$sizeClasses} {$classes}"]) }}
    data-category="{{ $category ?? 'uncategorized' }}">
    <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"
        data-category-icon="{{ $category ?? 'uncategorized' }}">
        {!! $icon !!}
    </svg>
    @if ($mode === 'diajukan')
        <span class="font-normal opacity-80">Kategori yang diajukan:</span>
    @endif
    <span>{{ $displayLabel }}</span>
</span>
