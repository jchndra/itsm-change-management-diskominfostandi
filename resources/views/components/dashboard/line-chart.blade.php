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
    $baseline = $top + $plotHeight;

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

    $smoothPointsFor = function (array $points) use ($top, $baseline): array {
        $count = count($points);

        if ($count < 3) {
            return $points;
        }

        $smooth = [];

        for ($index = 0; $index < $count - 1; $index++) {
            $p0 = $points[max(0, $index - 1)];
            $p1 = $points[$index];
            $p2 = $points[$index + 1];
            $p3 = $points[min($count - 1, $index + 2)];

            for ($step = 0; $step < 8; $step++) {
                $t = $step / 8;
                $t2 = $t * $t;
                $t3 = $t2 * $t;

                $x =
                    0.5 *
                    (2 * $p1[0] +
                        (-$p0[0] + $p2[0]) * $t +
                        (2 * $p0[0] - 5 * $p1[0] + 4 * $p2[0] - $p3[0]) * $t2 +
                        (-$p0[0] + 3 * $p1[0] - 3 * $p2[0] + $p3[0]) * $t3);
                $y =
                    0.5 *
                    (2 * $p1[1] +
                        (-$p0[1] + $p2[1]) * $t +
                        (2 * $p0[1] - 5 * $p1[1] + 4 * $p2[1] - $p3[1]) * $t2 +
                        (-$p0[1] + 3 * $p1[1] - 3 * $p2[1] + $p3[1]) * $t3);

                $smooth[] = [$x, max($top, min($baseline, $y))];
            }
        }

        $smooth[] = $points[$count - 1];

        return $smooth;
    };

    $polylineFor = function (array $points): string {
        return implode(' ', array_map(
            fn (array $point): string => sprintf('%.2f,%.2f', $point[0], $point[1]),
            $points
        ));
    };

    $areaPathFor = function (array $points) use ($baseline): string {
        if ($points === []) {
            return '';
        }

        $path = sprintf('M %.2f %.2f', $points[0][0], $baseline);

        foreach ($points as $point) {
            $path .= sprintf(' L %.2f %.2f', $point[0], $point[1]);
        }

        $lastPoint = $points[array_key_last($points)];
        $path .= sprintf(' L %.2f %.2f Z', $lastPoint[0], $baseline);

        return $path;
    };

    $lastNonZeroIndex = function (array $series): ?int {
        $lastIndex = null;

        foreach ($series as $index => $value) {
            if ((int) $value > 0) {
                $lastIndex = $index;
            }
        }

        return $lastIndex;
    };

    $primaryPoints = $pointsFor($primaryValues);
    $secondaryPoints = $pointsFor($secondaryValues);
    $primarySmoothPoints = $smoothPointsFor($primaryPoints);
    $secondarySmoothPoints = $smoothPointsFor($secondaryPoints);
    $primaryAreaPath = $areaPathFor($primarySmoothPoints);
    $secondaryAreaPath = $areaPathFor($secondarySmoothPoints);
    $primaryTotal = array_sum($primaryValues);
    $secondaryTotal = array_sum($secondaryValues);
    $primaryLastIndex = $lastNonZeroIndex($primaryValues);
    $secondaryLastIndex = $lastNonZeroIndex($secondaryValues);

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
    <figcaption class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div id="{{ $chartId }}-title" class="text-sm font-bold tracking-tight text-slate-900">
                {{ $title }}
            </div>
            <p id="{{ $chartId }}-description"
                class="{{ filled($description) ? 'mt-1 text-xs leading-relaxed text-slate-500' : 'sr-only' }}">
                {{ $description ?: $defaultDescription }}
            </p>
        </div>

        @if ($hasData)
            <div class="flex flex-wrap items-center gap-2 text-[11px] font-medium text-slate-600">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2.5 py-1.5 shadow-sm">
                    <span class="h-2 w-2 rounded-full bg-gradient-to-br from-blue-600 to-sky-400"
                        aria-hidden="true"></span>
                    <span>{{ $primaryLabel }}</span>
                    <span class="font-bold tabular-nums text-slate-900">{{ number_format($primaryTotal, 0, ',', '.') }}</span>
                </span>

                <span
                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2.5 py-1.5 shadow-sm">
                    <span class="h-2 w-2 rounded-full bg-gradient-to-br from-emerald-600 to-teal-400"
                        aria-hidden="true"></span>
                    <span>{{ $secondaryLabel }}</span>
                    <span class="font-bold tabular-nums text-slate-900">{{ number_format($secondaryTotal, 0, ',', '.') }}</span>
                </span>
            </div>
        @endif
    </figcaption>

    @if (! $hasData)
        <x-dashboard.empty-state title="Belum ada data grafik"
            description="Data akan tampil setelah ada aktivitas pada periode ini." />
    @else
        <div class="rounded-2xl border border-slate-100 bg-gradient-to-b from-slate-50/70 to-white p-2 sm:p-3">
            <svg role="img" aria-labelledby="{{ $chartId }}-title {{ $chartId }}-description"
                viewBox="0 0 {{ $width }} {{ $height }}" class="h-auto w-full" focusable="false"
                preserveAspectRatio="xMidYMid meet">
                <title>{{ $title }}</title>
                <desc>{{ $description ?: $defaultDescription }}</desc>

                <defs>
                    <linearGradient id="{{ $chartId }}-primary-area" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2563eb" stop-opacity="0.28" />
                        <stop offset="100%" stop-color="#2563eb" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="{{ $chartId }}-secondary-area" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#059669" stop-opacity="0.16" />
                        <stop offset="100%" stop-color="#059669" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="{{ $chartId }}-primary-line" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="#1d4ed8" />
                        <stop offset="55%" stop-color="#2563eb" />
                        <stop offset="100%" stop-color="#38bdf8" />
                    </linearGradient>
                    <linearGradient id="{{ $chartId }}-secondary-line" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="#047857" />
                        <stop offset="100%" stop-color="#34d399" />
                    </linearGradient>
                    <linearGradient id="{{ $chartId }}-plot" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#f8fafc" stop-opacity="0.8" />
                        <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
                    </linearGradient>
                </defs>

                <text x="14" y="{{ $top + ($plotHeight / 2) }}"
                    transform="rotate(-90 14 {{ $top + ($plotHeight / 2) }})" text-anchor="middle"
                    class="fill-slate-400 text-[9px] font-semibold uppercase tracking-[0.12em]">
                    Jumlah
                </text>

                <rect x="{{ $left }}" y="{{ $top }}" width="{{ $plotWidth }}" height="{{ $plotHeight }}"
                    rx="12" fill="url(#{{ $chartId }}-plot)" aria-hidden="true" />

                @foreach ($yTicks as $tick)
                    @php
                        $gridY = $yFor($tick);
                    @endphp
                    <line x1="{{ $left }}" y1="{{ $gridY }}" x2="{{ $width - $right }}"
                        y2="{{ $gridY }}" stroke="#e2e8f0" stroke-width="1"
                        @if ($tick === 0) stroke-opacity="1" @else stroke-dasharray="3 5" @endif />
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

                @if ($secondaryTotal > 0 && $secondaryAreaPath !== '')
                    <path d="{{ $secondaryAreaPath }}" fill="url(#{{ $chartId }}-secondary-area)"
                        class="chart-area-fade" aria-hidden="true" />
                @endif

                @if ($primaryTotal > 0 && $primaryAreaPath !== '')
                    <path d="{{ $primaryAreaPath }}" fill="url(#{{ $chartId }}-primary-area)"
                        class="chart-area-fade" aria-hidden="true" />
                @endif

                @if ($secondaryTotal > 0)
                    <polyline points="{{ $polylineFor($secondarySmoothPoints) }}" pathLength="1" fill="none"
                        stroke="url(#{{ $chartId }}-secondary-line)" stroke-width="2.25" stroke-linecap="round"
                        stroke-linejoin="round" class="chart-line-draw" />
                    @foreach ($secondaryValues as $index => $value)
                        @if ($value > 0)
                            <circle cx="{{ $secondaryPoints[$index][0] }}" cy="{{ $secondaryPoints[$index][1] }}"
                                r="3" fill="#059669" stroke="#ffffff" stroke-width="1.5" />
                        @endif
                    @endforeach
                    @if ($secondaryLastIndex !== null)
                        <circle cx="{{ $secondaryPoints[$secondaryLastIndex][0] }}"
                            cy="{{ $secondaryPoints[$secondaryLastIndex][1] }}" r="7" fill="#059669"
                            fill-opacity="0.12" aria-hidden="true" />
                        <circle cx="{{ $secondaryPoints[$secondaryLastIndex][0] }}"
                            cy="{{ $secondaryPoints[$secondaryLastIndex][1] }}" r="4" fill="#059669"
                            stroke="#ffffff" stroke-width="2" aria-hidden="true" />
                    @endif
                @endif

                @if ($primaryTotal > 0)
                    <polyline points="{{ $polylineFor($primarySmoothPoints) }}" pathLength="1" fill="none"
                        stroke="url(#{{ $chartId }}-primary-line)" stroke-width="2.75" stroke-linecap="round"
                        stroke-linejoin="round" class="chart-line-draw" />
                    @foreach ($primaryValues as $index => $value)
                        @if ($value > 0)
                            <circle cx="{{ $primaryPoints[$index][0] }}" cy="{{ $primaryPoints[$index][1] }}"
                                r="3" fill="#2563eb" stroke="#ffffff" stroke-width="1.5" />
                        @endif
                    @endforeach
                    @if ($primaryLastIndex !== null)
                        <circle cx="{{ $primaryPoints[$primaryLastIndex][0] }}"
                            cy="{{ $primaryPoints[$primaryLastIndex][1] }}" r="7" fill="#2563eb"
                            fill-opacity="0.14" aria-hidden="true" />
                        <circle cx="{{ $primaryPoints[$primaryLastIndex][0] }}"
                            cy="{{ $primaryPoints[$primaryLastIndex][1] }}" r="4" fill="#2563eb"
                            stroke="#ffffff" stroke-width="2" aria-hidden="true" />
                    @endif
                @endif

                {{-- Area hover untuk tooltip bawaan SVG pada setiap tanggal. --}}
                <g aria-hidden="true">
                    @foreach ($labels as $index => $label)
                        @php
                            $bandWidth = $pointCount > 1 ? $plotWidth / ($pointCount - 1) : $plotWidth;
                            $bandLeft = max($left, $xFor($index) - $bandWidth / 2);
                            $bandRight = min($width - $right, $xFor($index) + $bandWidth / 2);
                            $fullLabel = $tableLabels[$index] ?? $label;
                        @endphp
                        <rect x="{{ $bandLeft }}" y="{{ $top }}" width="{{ max(1, $bandRight - $bandLeft) }}"
                            height="{{ $plotHeight }}" fill="transparent" focusable="false">
                            <title>{{ $fullLabel }} — {{ $primaryLabel }}: {{ number_format((int) ($primaryValues[$index] ?? 0), 0, ',', '.') }}, {{ $secondaryLabel }}: {{ number_format((int) ($secondaryValues[$index] ?? 0), 0, ',', '.') }}</title>
                        </rect>
                    @endforeach
                </g>
            </svg>
        </div>

        <details class="mt-5 border-t border-slate-100 pt-4">
            <summary
                class="inline-flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-blue-700 transition hover:text-blue-800">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4.5 6.75h15m-15 5.25h15m-15 5.25h15" />
                </svg>
                Lihat data tabel
            </summary>
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
