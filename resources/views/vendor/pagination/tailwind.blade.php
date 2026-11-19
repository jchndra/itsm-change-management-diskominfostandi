@if ($paginator->hasPages())
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $windowStart = max(1, $currentPage - 2);
        $windowEnd = min($lastPage, $currentPage + 2);
        $windowPages = $paginator->getUrlRange($windowStart, $windowEnd);
        $linkClasses = 'grid h-9 min-w-9 place-items-center rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2';
        $activeClasses = 'grid h-9 min-w-9 place-items-center rounded-xl bg-gradient-to-br from-blue-600 to-sky-500 px-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/25';
        $arrowClasses = 'grid h-9 w-9 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2';
        $disabledClasses = 'grid h-9 w-9 cursor-not-allowed place-items-center rounded-xl border border-slate-200 bg-slate-50 text-slate-300';
    @endphp

    <nav role="navigation" aria-label="Navigasi halaman"
        class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-slate-500">
            Halaman <span class="font-semibold text-slate-700">{{ $currentPage }}</span>
            dari <span class="font-semibold text-slate-700">{{ $lastPage }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="Halaman sebelumnya" class="{{ $disabledClasses }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya"
                    class="{{ $arrowClasses }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </a>
            @endif

            @if ($windowStart > 1)
                <a href="{{ $paginator->url(1) }}" aria-label="Halaman 1" class="{{ $linkClasses }}">1</a>

                @if ($windowStart > 2)
                    <span class="grid h-9 min-w-9 place-items-center px-1 text-xs font-semibold text-slate-400"
                        aria-hidden="true">…</span>
                @endif
            @endif

            @foreach ($windowPages as $page => $url)
                @if ($page == $currentPage)
                    <span aria-current="page" class="{{ $activeClasses }}">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" aria-label="Halaman {{ $page }}"
                        class="{{ $linkClasses }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($windowEnd < $lastPage)
                @if ($windowEnd < $lastPage - 1)
                    <span class="grid h-9 min-w-9 place-items-center px-1 text-xs font-semibold text-slate-400"
                        aria-hidden="true">…</span>
                @endif

                <a href="{{ $paginator->url($lastPage) }}" aria-label="Halaman {{ $lastPage }}"
                    class="{{ $linkClasses }}">{{ $lastPage }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya"
                    class="{{ $arrowClasses }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
            @else
                <span aria-disabled="true" aria-label="Halaman berikutnya" class="{{ $disabledClasses }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif
