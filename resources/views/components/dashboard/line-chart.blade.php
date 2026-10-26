@props([
    'id' => null,
    'title',
    'description' => null,
    'labels' => [],
    'tableLabels' => [],
    'primary' => [],
    'secondary' => [],
    'primaryLabel' => 'RFC dibuat',
    'secondaryLabel' => 'Tiket dibuat',
])

@php
    $chartId = $id ?: 'line-chart-' . md5($title);
    $primaryValues = array_values($primary);
    $secondaryValues = array_values($secondary);
    $pointCount = max(count($labels), 1);
    $allValues = array_merge($primaryValues, $secondaryValues);
    $hasData = array_sum($allValues) > 0;
    $maxValue = max(array_merge([0], array_map('intval', $allValues)));
    $tickStep = max(1, (int) ceil($maxValue / 4));
    $axisMax = max(1, $tickStep * (int) max(1, ceil($maxValue / $tickStep)));
    $defaultDescription = 'Grafik garis jumlah ' . $primaryLabel . ' dan ' . $secondaryLabel . ' selama 30 hari terakhir.';

    $width = 760;
    $height = 300;
    $left = 52;
    $top = 24;
    $right = 18;
    $bottom = 52;
    $plotWidth = $width - $left - $right;
    $plotHeight = $height - $top - $bottom;

    $xFor = function (int $index) use ($pointCount, $left, $plotWidth): float {
        return $pointCount === 1
            ? $left + ($plotWidth / 2)
            : $left + (($index / ($pointCount - 1)) * $plotWidth);
    };

    $yFor = fn (int $value): float => $top + ($plotHeight - (($value / $axisMax) * $plotHeight));

    $pointsFor = function (array $series) use ($xFor, $yFor): array {
        $points = [];

        foreach ($series as $index => $value) {
            $points[] = [$xFor($index), $yFor((int) $value)];
        }

        return $points;
    };

    $polylineFor = function (array $points): string {
        return implode(' ', array_map(
            fn (array $point): string => sprintf('%.2f,%.2f', $point[0], $point[1]),
            $points
        ));
    };

    $primaryPoints = $pointsFor($primaryValues);
    $secondaryPoints = $pointsFor($secondaryValues);
    $primaryTotal = array_sum($primaryValues);
    $secondaryTotal = array_sum($secondaryValues);
    $tickIndexes = collect(range(0, 4))
        ->map(fn (int $position): int => (int) round($position * ($pointCount - 1) / 4))
        ->unique()
        ->sort()
        ->values();

    $yTicks = [];
    for ($tick = 0; $tick <= $axisMax; $tick += $tickStep) {
        $yTicks[] = $tick;
    }
@endphp

<figure class="m-0">
    <figcaption class="mb-4">
        <div id="{{ $chartId }}-title" class="text-sm font-semibold text-slate-800">{{ $title }}</div>
        <p id="{{ $chartId }}-description"
            class="{{ filled($description) ? 'mt-1 text-xs leading-relaxed text-slate-500' : 'sr-only' }}">
            {{ $description ?: $defaultDescription }}
        </p>
    </figcaption>

    @if (! $hasData)
        <x-dashboard.empty-state title="Belum ada data grafik"
            description="Data akan tampil setelah ada aktivitas pada periode ini." />
    @else
        <div class="mb-4 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-600">
            <span
                class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50/70 px-2.5 py-1.5">
                <svg class="h-2 w-7 overflow-visible" viewBox="0 0 28 8" aria-hidden="true">
                    <line x1="1" y1="4" x2="27" y2="4" stroke="#2563eb" stroke-width="2.5" />
                </svg>
                <span>{{ $primaryLabel }}</span>
                <span class="font-bold tabular-nums text-slate-800">{{ number_format($primaryTotal, 0, ',', '.') }}</span>
            </span>

            <span
                class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50/70 px-2.5 py-1.5">
                <svg class="h-2 w-7 overflow-visible" viewBox="0 0 28 8" aria-hidden="true">
                    <line x1="1" y1="4" x2="27" y2="4" stroke="#059669" stroke-width="2.5"
                        stroke-dasharray="5 3" />
                </svg>
                <span>{{ $secondaryLabel }}</span>
                <span class="font-bold tabular-nums text-slate-800">{{ number_format($secondaryTotal, 0, ',', '.') }}</span>
            </span>
        </div>

        <svg role="img" aria-labelledby="{{ $chartId }}-title {{ $chartId }}-description"
            viewBox="0 0 {{ $width }} {{ $height }}" class="h-auto w-full" focusable="false"
            preserveAspectRatio="xMidYMid meet">
            <title>{{ $title }}</title>
            <desc>{{ $description ?: $defaultDescription }}</desc>

            {{-- Label sumbu Y --}}
            <text x="14" y="{{ $top + ($plotHeight / 2) }}" transform="rotate(-90 14 {{ $top + ($plotHeight / 2) }})"
                text-anchor="middle"
                class="fill-slate-400 text-[9px] font-semibold uppercase tracking-[0.12em]">
                Jumlah
            </text>

            @foreach ($yTicks as $tick)
                @php
                    $gridY = $yFor($tick);
                @endphp
                <line x1="{{ $left }}" y1="{{ $gridY }}" x2="{{ $width - $right }}"
                    y2="{{ $gridY }}" stroke="#e2e8f0" stroke-width="1"
                    @if ($tick === 0) stroke-opacity="1" @else stroke-dasharray="3 4" @endif />
                <text x="{{ $left - 10 }}" y="{{ $gridY + 3.5 }}" text-anchor="end"
                    class="fill-slate-400 text-[10px] tabular-nums">{{ number_format($tick, 0, ',', '.') }}</text>
            @endforeach

            @foreach ($tickIndexes as $index)
                <text x="{{ $xFor($index) }}" y="{{ $height - 30 }}" text-anchor="middle"
                    class="fill-slate-400 text-[10px]">{{ $labels[$index] ?? '' }}</text>
            @endforeach

            <text x="{{ $width - $right }}" y="{{ $height - 8 }}" text-anchor="end"
                class="fill-slate-400 text-[9px] font-semibold uppercase tracking-[0.1em]">
                Tanggal (WIB)
            </text>

            @if ($primaryTotal > 0)
                <polyline points="{{ $polylineFor($primaryPoints) }}" fill="none" stroke="#2563eb"
                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                @foreach ($primaryValues as $index => $value)
                    @if ($value > 0)
                        <circle cx="{{ $primaryPoints[$index][0] }}" cy="{{ $primaryPoints[$index][1] }}"
                            r="3" fill="#2563eb" stroke="#ffffff" stroke-width="1.5" />
                    @endif
                @endforeach
            @endif

            @if ($secondaryTotal > 0)
                <polyline points="{{ $polylineFor($secondaryPoints) }}" fill="none" stroke="#059669"
                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="6 4" />
                @foreach ($secondaryValues as $index => $value)
                    @if ($value > 0)
                        <circle cx="{{ $secondaryPoints[$index][0] }}" cy="{{ $secondaryPoints[$index][1] }}"
                            r="3" fill="#059669" stroke="#ffffff" stroke-width="1.5" />
                    @endif
                @endforeach
            @endif

            {{-- Area hover untuk tooltip bawaan SVG pada setiap tanggal. --}}
            <g aria-hidden="true">
                @foreach ($labels as $index => $label)
                    @php
                        $bandWidth = $pointCount > 1 ? $plotWidth / ($pointCount - 1) : $plotWidth;
                        $bandLeft = max($left, $xFor($index) - ($bandWidth / 2));
                        $bandRight = min($width - $right, $xFor($index) + ($bandWidth / 2));
                        $fullLabel = $tableLabels[$index] ?? $label;
                    @endphp
                    <rect x="{{ $bandLeft }}" y="{{ $top }}" width="{{ max(1, $bandRight - $bandLeft) }}"
                        height="{{ $plotHeight }}" fill="transparent" focusable="false">
                        <title>{{ $fullLabel }} — {{ $primaryLabel }}: {{ number_format((int) ($primaryValues[$index] ?? 0), 0, ',', '.') }}, {{ $secondaryLabel }}: {{ number_format((int) ($secondaryValues[$index] ?? 0), 0, ',', '.') }}</title>
                    </rect>
                @endforeach
            </g>
        </svg>

        <details class="mt-5 border-t border-slate-100 pt-4">
            <summary class="cursor-pointer text-xs font-semibold text-blue-700">Lihat data tabel</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[460px] text-left text-xs text-slate-600">
                    <caption class="sr-only">{{ $title }} — data harian</caption>
                    <thead class="border-b border-slate-100 text-[10px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="py-2 pr-3 font-semibold">Tanggal</th>
                            <th scope="col" class="py-2 pr-3 text-right font-semibold">{{ $primaryLabel }}</th>
                            <th scope="col" class="py-2 text-right font-semibold">{{ $secondaryLabel }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($labels as $index => $label)
                            <tr>
                                <th scope="row" class="py-2 pr-3 text-left font-medium text-slate-600">
                                    {{ $tableLabels[$index] ?? $label }}
                                </th>
                                <td class="py-2 pr-3 text-right tabular-nums">
                                    {{ number_format((int) ($primaryValues[$index] ?? 0), 0, ',', '.') }}
                                </td>
                                <td class="py-2 text-right tabular-nums">
                                    {{ number_format((int) ($secondaryValues[$index] ?? 0), 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-200 font-semibold text-slate-700">
                        <tr>
                            <th scope="row" class="pt-2 pr-3 text-left">Total</th>
                            <td class="pt-2 pr-3 text-right tabular-nums">{{ number_format($primaryTotal, 0, ',', '.') }}</td>
                            <td class="pt-2 text-right tabular-nums">{{ number_format($secondaryTotal, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </details>
    @endif
</figure>
