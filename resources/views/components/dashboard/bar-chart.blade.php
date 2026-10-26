@props([
    'id' => null,
    'title',
    'description' => null,
    'items' => [],
    'groupRemaining' => false,
    'maxVisible' => 6,
    'showTerminalGroups' => false,
    'columns' => 1,
])

@php
    $chartId = $id ?: 'bar-chart-' . md5($title);
    $allItems = collect($items)->values();
    $total = $allItems->sum(fn (array $item): int => (int) ($item['value'] ?? 0));
    $hasData = $total > 0;

    $activeTotal = $allItems
        ->reject(fn (array $item): bool => (bool) ($item['is_terminal'] ?? false))
        ->sum(fn (array $item): int => (int) ($item['value'] ?? 0));
    $terminalTotal = $total - $activeTotal;

    $visibleItems = $allItems;
    $remainingItems = collect();

    if ($groupRemaining && $allItems->count() > $maxVisible) {
        $visibleItems = $allItems->take($maxVisible);
        $remainingItems = $allItems->slice($maxVisible)->values();

        if ($remainingItems->isNotEmpty()) {
            $visibleItems = $visibleItems->push([
                'status' => '__remaining',
                'label' => 'Lainnya (' . $remainingItems->count() . ' status)',
                'value' => $remainingItems->sum(fn (array $item): int => (int) ($item['value'] ?? 0)),
                'is_terminal' => false,
                'is_remaining' => true,
            ]);
        }
    }

    $tones = [
        'draft' => [
            'dot' => 'bg-slate-400',
            'bar' => 'from-slate-400 to-slate-300',
            'hex' => '#94a3b8',
        ],
        'submitted' => [
            'dot' => 'bg-blue-500',
            'bar' => 'from-blue-500 to-sky-400',
            'hex' => '#3b82f6',
        ],
        'needs_revision' => [
            'dot' => 'bg-amber-500',
            'bar' => 'from-amber-500 to-yellow-400',
            'hex' => '#f59e0b',
        ],
        'ticket_created' => [
            'dot' => 'bg-indigo-500',
            'bar' => 'from-indigo-500 to-violet-400',
            'hex' => '#6366f1',
        ],
        'returned_to_admin' => [
            'dot' => 'bg-indigo-400',
            'bar' => 'from-indigo-400 to-blue-300',
            'hex' => '#818cf8',
        ],
        'approved' => [
            'dot' => 'bg-emerald-500',
            'bar' => 'from-emerald-500 to-teal-400',
            'hex' => '#10b981',
        ],
        'in_progress' => [
            'dot' => 'bg-sky-500',
            'bar' => 'from-sky-500 to-cyan-400',
            'hex' => '#0ea5e9',
        ],
        'implemented' => [
            'dot' => 'bg-amber-400',
            'bar' => 'from-amber-400 to-orange-300',
            'hex' => '#fbbf24',
        ],
        'failed' => [
            'dot' => 'bg-rose-600',
            'bar' => 'from-rose-600 to-pink-500',
            'hex' => '#e11d48',
        ],
        'closed' => [
            'dot' => 'bg-slate-500',
            'bar' => 'from-slate-500 to-slate-400',
            'hex' => '#64748b',
        ],
        'rejected' => [
            'dot' => 'bg-rose-500',
            'bar' => 'from-rose-500 to-orange-400',
            'hex' => '#f43f5e',
        ],
        'cancelled' => [
            'dot' => 'bg-slate-400',
            'bar' => 'from-slate-400 to-slate-300',
            'hex' => '#94a3b8',
        ],
    ];

    $fallbackTone = [
        'dot' => 'bg-slate-400',
        'bar' => 'from-slate-400 to-slate-300',
        'hex' => '#94a3b8',
    ];

    $toneFor = fn (array $item): array => ($item['is_remaining'] ?? false)
        ? $fallbackTone
        : ($tones[$item['status']] ?? $fallbackTone);

    $listClasses = (int) $columns > 1
        ? 'grid gap-x-5 gap-y-4 sm:grid-cols-2'
        : 'space-y-4';

    $circumference = 2 * M_PI * 54;
    $segments = [];
    $cumulative = 0.0;
    $visibleCount = $visibleItems->count();

    foreach ($visibleItems as $item) {
        $value = (int) ($item['value'] ?? 0);
        $segmentLength = $total > 0 ? ($value / $total) * $circumference : 0;
        $gap = $visibleCount > 1 && $segmentLength > 0 ? min(2.6, $segmentLength / 2) : 0;
        $dashLength = max(0, $segmentLength - $gap);
        $offset = -1 * (($cumulative / max(1, $total)) * $circumference);

        $segments[] = [
            'item' => $item,
            'value' => $value,
            'share' => $total > 0 ? ($value / $total) * 100 : 0,
            'tone' => $toneFor($item),
            'dasharray' => sprintf('%.3f %.3f', $dashLength, max(0, $circumference - $dashLength)),
            'offset' => sprintf('%.3f', $offset),
        ];

        $cumulative += $value;
    }
@endphp

<figure class="m-0">
    <figcaption id="{{ $chartId }}-title" class="mb-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="text-sm font-bold tracking-tight text-slate-900">{{ $title }}</div>
            @if ($hasData)
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-slate-500 shadow-sm">
                    <span class="font-bold tabular-nums text-slate-800">{{ number_format($total, 0, ',', '.') }}</span>
                    RFC total
                </span>
            @endif
        </div>
        @if (filled($description))
            <p id="{{ $chartId }}-description" class="mt-1 text-xs leading-relaxed text-slate-500">{{ $description }}
            </p>
        @endif
    </figcaption>

    @if (! $hasData)
        <x-dashboard.empty-state title="Belum ada data distribusi"
            description="Distribusi status akan tampil setelah ada RFC." />
    @else
        <div class="@container">
            <div class="grid items-center gap-6 @lg:grid-cols-[190px_minmax(0,1fr)]">
                <div class="relative mx-auto h-[190px] w-[190px] shrink-0">
                    <svg viewBox="0 0 160 160" class="h-full w-full drop-shadow-sm" aria-hidden="true"
                        focusable="false">
                        <circle cx="80" cy="80" r="54" fill="none" stroke="#f1f5f9" stroke-width="20" />
                        <g transform="rotate(-90 80 80)">
                            @foreach ($segments as $segment)
                                <circle cx="80" cy="80" r="54" fill="none"
                                    stroke="{{ $segment['tone']['hex'] }}" stroke-width="20"
                                    stroke-dasharray="{{ $segment['dasharray'] }}"
                                    stroke-dashoffset="{{ $segment['offset'] }}"
                                    class="transition-opacity duration-200 hover:opacity-75">
                                    <title>{{ $segment['item']['label'] }}: {{ number_format($segment['value'], 0, ',', '.') }} RFC ({{ number_format($segment['share'], 1, ',', '.') }}%)</title>
                                </circle>
                            @endforeach
                        </g>
                    </svg>

                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span
                            class="text-[28px] font-bold leading-none tabular-nums tracking-[-0.04em] text-slate-900">{{ number_format($total, 0, ',', '.') }}</span>
                        <span class="mt-1 text-[9px] font-bold uppercase tracking-[0.18em] text-slate-400">RFC</span>
                    </div>
                </div>

            <div class="min-w-0">
                @if ($showTerminalGroups)
                    <div class="mb-4 grid grid-cols-2 gap-2" role="group"
                        aria-label="Ringkasan status aktif dan terminal">
                        <div
                            class="rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white px-3 py-2.5 shadow-sm">
                            <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-blue-600">Status aktif</p>
                            <p class="mt-1 text-lg font-bold leading-none tabular-nums text-blue-950">
                                {{ number_format($activeTotal, 0, ',', '.') }}
                                <span class="text-[10px] font-medium text-blue-500">RFC</span>
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white px-3 py-2.5 shadow-sm">
                            <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-slate-500">Terminal</p>
                            <p class="mt-1 text-lg font-bold leading-none tabular-nums text-slate-700">
                                {{ number_format($terminalTotal, 0, ',', '.') }}
                                <span class="text-[10px] font-medium text-slate-400">RFC</span>
                            </p>
                        </div>
                    </div>
                @endif

                <ul class="{{ $listClasses }}">
                    @foreach ($segments as $segment)
                        @php
                            $item = $segment['item'];
                            $isTerminal = (bool) ($item['is_terminal'] ?? false);
                        @endphp
                        <li class="min-w-0">
                            <div class="flex items-start justify-between gap-3 text-xs">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span
                                        class="h-2.5 w-2.5 shrink-0 rounded-full ring-2 ring-white shadow-sm {{ $segment['tone']['dot'] }}"
                                        aria-hidden="true"></span>
                                    <span class="min-w-0 truncate font-semibold text-slate-700"
                                        title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                                    @if ($showTerminalGroups && $isTerminal)
                                        <span
                                            class="inline-flex shrink-0 items-center rounded bg-slate-100 px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide text-slate-500">
                                            Terminal
                                        </span>
                                    @endif
                                </div>
                                <div class="shrink-0 whitespace-nowrap text-right tabular-nums">
                                    <span class="font-bold text-slate-900">{{ number_format($segment['value'], 0, ',', '.') }}</span>
                                    <span
                                        class="ml-1 text-[10px] text-slate-400">{{ number_format($segment['share'], 1, ',', '.') }}%</span>
                                </div>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                <div class="h-full rounded-full bg-gradient-to-r {{ $segment['tone']['bar'] }}"
                                    style="width: {{ number_format($segment['share'], 2, '.', '') }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
            </div>
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
                <table class="w-full min-w-[300px] text-left text-xs text-slate-600">
                    <caption class="sr-only">{{ $title }} — data status RFC</caption>
                    <thead class="border-b border-slate-100 text-[10px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="py-2 pr-3 font-semibold">Status</th>
                            <th scope="col" class="py-2 pr-3 text-right font-semibold">Jumlah RFC</th>
                            <th scope="col" class="py-2 text-right font-semibold">Porsi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($allItems as $item)
                            @php
                                $value = (int) ($item['value'] ?? 0);
                                $share = $total > 0 ? ($value / $total) * 100 : 0;
                            @endphp
                            <tr>
                                <th scope="row" class="py-2 pr-3 text-left font-medium text-slate-600">
                                    {{ $item['label'] }}
                                    @if ($showTerminalGroups && ($item['is_terminal'] ?? false))
                                        <span class="ml-1 text-[10px] font-semibold text-slate-400">(terminal)</span>
                                    @endif
                                </th>
                                <td class="py-2 pr-3 text-right tabular-nums">
                                    {{ number_format($value, 0, ',', '.') }}
                                </td>
                                <td class="py-2 text-right tabular-nums text-slate-500">
                                    {{ number_format($share, 1, ',', '.') }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-200 font-semibold text-slate-700">
                        <tr>
                            <th scope="row" class="pt-2 pr-3 text-left">Total</th>
                            <td class="pt-2 pr-3 text-right tabular-nums">{{ number_format($total, 0, ',', '.') }}</td>
                            <td class="pt-2 text-right tabular-nums">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </details>
    @endif
</figure>
