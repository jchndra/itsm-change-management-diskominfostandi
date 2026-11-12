<div class="space-y-5 sm:space-y-6">
    <x-dashboard.hero-banner class="dashboard-rise" title="Kalender Jadwal" :user-name="auth()->user()->name"
        :role-label="auth()->user()->getRoleNames()->first() ?? 'Tim Rilis'"
        :description="'Jadwal pengerjaan RFC per bulan. Klik bar untuk membuka detail RFC.'" :date-time="$dateTime" />

    <x-dashboard.panel class="dashboard-rise dashboard-rise-1" id="calendar-month-heading" title="Kalender {{ $monthLabel }}"
        title-live description="Segmen tugas ditampilkan per minggu, Senin sampai Minggu.">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
        </x-slot:icon>

        <x-slot:actions>
            @if ($isChangeManager)
                <select wire:model.live="team" aria-label="Filter anggota Tim Rilis"
                    class="min-h-11 rounded-lg border-slate-300 bg-white text-xs font-semibold text-slate-700 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua tim</option>
                    @foreach ($teamOptions as $option)
                        <option value="{{ $option->id }}">{{ $option->user?->name ?? 'Anggota #'.$option->id }}</option>
                    @endforeach
                </select>
            @endif

            <div class="inline-flex items-center overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <button type="button" wire:click="previousMonth" wire:loading.attr="disabled" aria-label="Bulan sebelumnya"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center text-slate-500 transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
                    </svg>
                </button>
                <button type="button" wire:click="currentMonth" wire:loading.attr="disabled"
                    class="inline-flex min-h-11 items-center justify-center border-x border-slate-200 px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                    Hari ini
                </button>
                <button type="button" wire:click="nextMonth" wire:loading.attr="disabled" aria-label="Bulan berikutnya"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center text-slate-500 transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                </button>
            </div>
        </x-slot:actions>

        @unless ($hasTasks)
            <div class="p-5">
                <x-dashboard.empty-state title="Belum ada jadwal"
                    description="Tugas Tim Rilis yang memiliki estimasi atau jadwal akan tampil di kalender ini." />
            </div>
        @endunless

        {{-- Grid bulan (layar sedang ke atas) --}}
        <div class="hidden overflow-hidden md:block" x-data="{ openWeek: null }">
            <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/70">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dayLabel)
                    <div class="px-2 py-2 text-center text-xs font-bold uppercase tracking-wider text-slate-500">
                        {{ $dayLabel }}
                    </div>
                @endforeach
            </div>

            @foreach ($weeks as $week)
                @php
                    $weekKey = $week['start']->format('Ymd');
                    $hiddenPanelId = 'calendar-hidden-'.$weekKey;
                @endphp
                <div class="border-b border-slate-100 last:border-b-0">
                    <div class="grid grid-cols-7">
                        @foreach ($week['days'] as $day)
                            <div class="border-r border-slate-100 px-2 py-1.5 text-right last:border-r-0">
                                <span
                                    class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-bold {{ $day['is_today'] ? 'bg-blue-600 text-white' : ($day['in_month'] ? 'text-slate-700' : 'text-slate-300') }}">
                                    {{ $day['date']->day }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="relative grid grid-cols-7 auto-rows-[1.75rem] gap-y-1 px-1 py-1.5">
                        @foreach ($week['segments'] as $segment)
                            @php
                                $item = $segment['item'];
                                $rfc = $item['task']->changeRequest;
                                $identifier = $rfc?->displayIdentifier() ?? 'RFC';
                                $dateRange = $item['display_start']->copy()->locale('id')->translatedFormat('d M')
                                    .'–'.$item['display_end']->copy()->locale('id')->translatedFormat('d M');
                                $tooltip = $identifier.' • '.$dateRange.' • '.$item['status']['label'];
                                $ariaLabel = $identifier.', '.$dateRange.', '.$item['status']['label'];
                                if ($item['conflict']) {
                                    $tooltip .= ' • Bentrok dengan '.implode(', ', $item['conflict_labels']);
                                    $ariaLabel .= ', bentrok dengan '.implode(', ', $item['conflict_labels']);
                                }
                            @endphp
                            <a href="{{ route('change-requests.show', ['rfc' => $item['task']->change_request_id]) }}"
                                wire:navigate wire:key="calendar-bar-{{ $week['start']->format('Ymd') }}-{{ $item['task']->id }}"
                                style="grid-column: {{ $segment['col_start'] + 1 }} / span {{ $segment['span'] }}; grid-row: {{ $segment['lane'] + 1 }};"
                                title="{{ $tooltip }}" aria-label="{{ $ariaLabel }}"
                                class="m-0.5 flex min-w-0 items-center gap-1 overflow-hidden rounded-md px-2 text-xs font-bold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 {{ $item['status']['bar'] }} {{ $item['conflict'] ? 'ring-2 ring-amber-400' : '' }}">
                                <span class="truncate">{{ $identifier }}</span>
                                <span class="sr-only">{{ $item['status']['label'] }}</span>
                                @if ($item['conflict'])
                                    <span class="ml-auto shrink-0 text-xs font-black text-amber-100" aria-hidden="true">!</span>
                                    <span class="sr-only">Bentrok</span>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    @if ($week['hidden'] > 0)
                        <div class="px-2 pb-1.5">
                            <button type="button" x-on:click="openWeek = openWeek === '{{ $weekKey }}' ? null : '{{ $weekKey }}'"
                                x-bind:aria-expanded="openWeek === '{{ $weekKey }}' ? 'true' : 'false'"
                                aria-expanded="false" aria-controls="{{ $hiddenPanelId }}"
                                aria-label="Tampilkan {{ $week['hidden'] }} tugas lain pada minggu ini"
                                class="inline-flex min-h-11 items-center rounded-lg px-2 text-xs font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                                +{{ $week['hidden'] }} lagi
                            </button>

                            <ul id="{{ $hiddenPanelId }}" data-calendar-hidden-tasks x-cloak
                                x-show="openWeek === '{{ $weekKey }}'"
                                class="mt-1 space-y-1 rounded-lg border border-slate-200 bg-slate-50/80 p-2">
                                @foreach ($week['hidden_segments'] as $segment)
                                    @php
                                        $item = $segment['item'];
                                        $rfc = $item['task']->changeRequest;
                                        $identifier = $rfc?->displayIdentifier() ?? 'RFC';
                                        $dateRange = $item['display_start']->copy()->locale('id')->translatedFormat('d M')
                                            .'–'.$item['display_end']->copy()->locale('id')->translatedFormat('d M');
                                        $tooltip = $identifier.' • '.$dateRange.' • '.$item['status']['label'];
                                        $ariaLabel = $identifier.', '.$dateRange.', '.$item['status']['label'];
                                        if ($item['conflict']) {
                                            $tooltip .= ' • Bentrok dengan '.implode(', ', $item['conflict_labels']);
                                            $ariaLabel .= ', bentrok dengan '.implode(', ', $item['conflict_labels']);
                                        }
                                    @endphp
                                    <li>
                                        <a href="{{ route('change-requests.show', ['rfc' => $item['task']->change_request_id]) }}"
                                            wire:navigate
                                            wire:key="calendar-hidden-{{ $weekKey }}-{{ $item['task']->id }}"
                                            title="{{ $tooltip }}" aria-label="{{ $ariaLabel }}"
                                            class="flex min-h-11 items-center gap-2 rounded-md border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-blue-200 hover:bg-blue-50/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                                            <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $item['status']['dot'] }}"
                                                aria-hidden="true"></span>
                                            <span class="min-w-0 flex-1 truncate">{{ $identifier }}</span>
                                            <span class="shrink-0 whitespace-nowrap text-slate-500">{{ $dateRange }}</span>
                                            <span
                                                class="shrink-0 whitespace-nowrap rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $item['status']['label'] }}</span>
                                            @if ($item['conflict'])
                                                <span
                                                    class="shrink-0 whitespace-nowrap rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">Bentrok</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Agenda (layar kecil) --}}
        <div class="space-y-3 p-4 md:hidden">
            @forelse ($agenda as $day)
                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        {{ $day['date']->copy()->locale('id')->translatedFormat('l, d M Y') }}
                    </p>
                    <ul class="mt-2 space-y-2">
                        @foreach ($day['tasks'] as $item)
                            @php
                                $rfc = $item['task']->changeRequest;
                            @endphp
                            <li>
                                <a href="{{ route('change-requests.show', ['rfc' => $item['task']->change_request_id]) }}"
                                    wire:navigate
                                    class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2 text-xs transition hover:border-blue-200 hover:bg-blue-50/60">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $item['status']['dot'] }}"
                                        aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-bold text-slate-800">
                                            {{ $rfc?->displayIdentifier() ?? 'RFC' }}
                                        </span>
                                        <span class="block truncate text-xs text-slate-500">
                                            {{ $rfc?->title }}
                                        </span>
                                    </span>
                                    <span
                                        class="shrink-0 whitespace-nowrap rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                                        {{ $item['status']['label'] }}
                                    </span>
                                    @if ($item['conflict'])
                                        <span
                                            class="shrink-0 whitespace-nowrap rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800"
                                            title="Bentrok dengan {{ implode(', ', $item['conflict_labels']) }}">
                                            Bentrok
                                        </span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-6 text-center text-xs text-slate-500">
                    Tidak ada agenda pada bulan ini.
                </p>
            @endforelse
        </div>

        {{-- Legenda --}}
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3 text-xs font-semibold text-slate-600">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>Terlambat</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>Dikerjakan</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>Belum dimulai</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Selesai</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>Rollback</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-400"></span>Jadwal bentrok</span>
        </div>
    </x-dashboard.panel>
</div>
