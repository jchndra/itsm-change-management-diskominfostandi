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

    $toneMap = [
        'draft' => 'bg-slate-400',
        'submitted' => 'bg-blue-500',
        'needs_revision' => 'bg-amber-500',
        'ticket_created' => 'bg-indigo-500',
        'returned_to_admin' => 'bg-indigo-400',
        'approved' => 'bg-emerald-500',
        'in_progress' => 'bg-sky-500',
        'implemented' => 'bg-amber-400',
        'failed' => 'bg-rose-600',
        'closed' => 'bg-slate-500',
        'rejected' => 'bg-rose-500',
        'cancelled' => 'bg-slate-400',
    ];

    $toneFor = fn (array $item): string => ($item['is_remaining'] ?? false)
        ? 'bg-slate-400'
        : ($toneMap[$item['status']] ?? 'bg-slate-400');

    $listClasses = (int) $columns > 1
        ? 'grid gap-x-6 gap-y-3 sm:grid-cols-2'
        : 'space-y-3';
@endphp

<figure class="m-0">
    <figcaption id="{{ $chartId }}-title" class="mb-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="text-sm font-semibold text-slate-800">{{ $title }}</div>
            @if ($hasData)
                <span
                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                    <span class="font-bold tabular-nums text-slate-800">{{ number_format($total, 0, ',', '.') }}</span>
                    RFC total
                </span>
            @endif
        </div>
        @if (filled($description))
            <p id="{{ $chartId }}-description" class="mt-1 text-xs leading-relaxed text-slate-500">{{ $description }}</p>
        @endif
    </figcaption>

    @if (! $hasData)
        <x-dashboard.empty-state title="Belum ada data distribusi"
            description="Distribusi status akan tampil setelah ada RFC." />
    @else
        @if ($showTerminalGroups)
            <div class="mb-4 grid grid-cols-2 gap-2" role="group"
                aria-label="Ringkasan status aktif dan terminal">
                <div class="rounded-xl border border-blue-100 bg-blue-50/60 px-3 py-2.5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-blue-600">Status aktif</p>
                    <p class="mt-1 text-lg font-bold leading-none tabular-nums text-blue-900">
                        {{ number_format($activeTotal, 0, ',', '.') }}
                        <span class="text-[11px] font-medium text-blue-600">RFC</span>
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Terminal</p>
                    <p class="mt-1 text-lg font-bold leading-none tabular-nums text-slate-700">
                        {{ number_format($terminalTotal, 0, ',', '.') }}
                        <span class="text-[11px] font-medium text-slate-500">RFC</span>
                    </p>
                </div>
            </div>
        @endif

        <ul class="{{ $listClasses }}">
            @foreach ($visibleItems as $item)
                @php
                    $value = (int) ($item['value'] ?? 0);
                    $share = $total > 0 ? ($value / $total) * 100 : 0;
                    $tone = $toneFor($item);
                    $isTerminal = (bool) ($item['is_terminal'] ?? false);
                @endphp
                <li>
                    <div class="flex items-end justify-between gap-3 text-xs">
                        <div class="flex min-w-0 items-center gap-1.5">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $tone }}" aria-hidden="true"></span>
                            <span class="truncate font-medium text-slate-700">{{ $item['label'] }}</span>
                            @if ($showTerminalGroups && $isTerminal)
                                <span
                                    class="inline-flex shrink-0 items-center rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-slate-500">
                                    Terminal
                                </span>
                            @endif
                        </div>
                        <div class="shrink-0 whitespace-nowrap text-right tabular-nums">
                            <span class="font-semibold text-slate-800">{{ number_format($value, 0, ',', '.') }}</span>
                            <span class="text-[10px] text-slate-400">({{ number_format($share, 1, ',', '.') }}%)</span>
                        </div>
                    </div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                        <div class="h-full rounded-full {{ $tone }}"
                            style="width: {{ number_format($share, 2, '.', '') }}%"></div>
                    </div>
                </li>
            @endforeach
        </ul>

        <details class="mt-5 border-t border-slate-100 pt-4">
            <summary class="cursor-pointer text-xs font-semibold text-blue-700">Lihat data tabel</summary>
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
