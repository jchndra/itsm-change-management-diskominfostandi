@props(['rfcNumber', 'label', 'actorName', 'occurredAt', 'isLast' => false])

@php
    $normalizedLabel = \Illuminate\Support\Str::lower($label);
    $tone = match (true) {
        str_contains($normalizedLabel, 'ditolak') || str_contains($normalizedLabel, 'gagal') => [
            'tile' => 'bg-rose-50 text-rose-600 ring-rose-100',
            'icon' => 'x',
        ],
        str_contains($normalizedLabel, 'disetujui') => [
            'tile' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
            'icon' => 'check',
        ],
        str_contains($normalizedLabel, 'ditutup') => [
            'tile' => 'bg-slate-100 text-slate-600 ring-slate-200',
            'icon' => 'lock',
        ],
        str_contains($normalizedLabel, 'implementasi') || str_contains($normalizedLabel, 'evaluasi') => [
            'tile' => 'bg-amber-50 text-amber-600 ring-amber-100',
            'icon' => 'wrench',
        ],
        str_contains($normalizedLabel, 'risiko') => [
            'tile' => 'bg-indigo-50 text-indigo-600 ring-indigo-100',
            'icon' => 'shield',
        ],
        str_contains($normalizedLabel, 'lampiran') => [
            'tile' => 'bg-cyan-50 text-cyan-600 ring-cyan-100',
            'icon' => 'paperclip',
        ],
        str_contains($normalizedLabel, 'revisi') => [
            'tile' => 'bg-amber-50 text-amber-600 ring-amber-100',
            'icon' => 'pencil',
        ],
        str_contains($normalizedLabel, 'tiket') => [
            'tile' => 'bg-blue-50 text-blue-600 ring-blue-100',
            'icon' => 'ticket',
        ],
        str_contains($normalizedLabel, 'diajukan') => [
            'tile' => 'bg-blue-50 text-blue-600 ring-blue-100',
            'icon' => 'send',
        ],
        str_contains($normalizedLabel, 'dikembalikan') => [
            'tile' => 'bg-indigo-50 text-indigo-600 ring-indigo-100',
            'icon' => 'return',
        ],
        str_contains($normalizedLabel, 'dibatalkan') => [
            'tile' => 'bg-slate-100 text-slate-500 ring-slate-200',
            'icon' => 'ban',
        ],
        default => [
            'tile' => 'bg-indigo-50 text-indigo-600 ring-indigo-100',
            'icon' => 'pulse',
        ],
    };
    $fullDate = $occurredAt->copy()->timezone(config('app.timezone'))->locale('id');
@endphp

<li {{ $attributes->merge(['class' => 'relative flex items-start gap-3 px-5 py-4']) }}>
    @unless ($isLast)
        <span class="absolute left-[37px] top-12 h-[calc(100%-2.5rem)] w-px bg-slate-100" aria-hidden="true"></span>
    @endunless

    <span
        class="relative z-10 mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl ring-1 {{ $tone['tile'] }}">
        @switch($tone['icon'])
            @case('x')
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                @break
            @case('check')
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                @break
            @case('lock')
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0 1 19.5 12.75v6A2.25 2.25 0 0 1 17.25 21H6.75a2.25 2.25 0 0 1-2.25-2.25v-6a2.25 2.25 0 0 1 2.25-2.25Z" />
                </svg>
                @break
            @case('wrench')
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M11.42 6.42a4.5 4.5 0 0 0 6.16 6.16l3.17 3.17a2.25 2.25 0 0 1-3.18 3.18l-3.17-3.17a4.5 4.5 0 0 1-6.16-6.16l2.12 2.12 3.18-3.18-2.12-2.12Z" />
                </svg>
                @break
            @case('shield')
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M12 3 4.5 6v5.25c0 4.42 3.04 8.36 7.5 9.75 4.46-1.39 7.5-5.33 7.5-9.75V6L12 3Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9.75 12 1.5 1.5 3-3" />
                </svg>
                @break
            @case('paperclip')
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="m18.75 7.5-7.5 7.5a3 3 0 0 1-4.24-4.24l8.49-8.49a4.5 4.5 0 0 1 6.36 6.36l-8.49 8.49a6 6 0 0 1-8.49-8.49l7.07-7.07" />
                </svg>
                @break
            @case('pencil')
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="m16.86 4.49 2.65 2.65M4.5 19.5l4.04-.81L19.5 7.73a1.87 1.87 0 0 0-2.65-2.65L5.31 15.96 4.5 19.5Z" />
                </svg>
                @break
            @case('ticket')
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M3.75 7.5h16.5v9H3.75v-9Zm0 4.5h4.5l1.5 2.25h4.5l1.5-2.25h4.5" />
                </svg>
                @break
            @case('send')
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="m21 3-7.5 18-3-7.5L3 10.5 21 3Zm-10.5 10.5L21 3" />
                </svg>
                @break
            @case('return')
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M9 10.5 4.5 6 9 1.5M4.5 6h9a6 6 0 1 1 0 12H9" />
                </svg>
                @break
            @case('ban')
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M18.36 5.64 5.64 18.36M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                @break
            @default
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M3.75 12h3l2.25-4.5 3 9 2.25-4.5h6" />
                </svg>
        @endswitch
    </span>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700 ring-1 ring-blue-100">
                {{ $rfcNumber }}
            </span>
            <span class="text-sm font-medium leading-snug text-slate-700">{{ $label }}</span>
        </div>

        <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
            <span class="font-medium text-slate-600">{{ $actorName }}</span>
            <span class="text-slate-300" aria-hidden="true">·</span>
            <time datetime="{{ $occurredAt->toIso8601String() }}"
                title="{{ $fullDate->translatedFormat('d F Y H:i') }} WIB">
                {{ $occurredAt->copy()->locale('id')->diffForHumans() }}
            </time>
        </div>
    </div>
</li>
