<div class="space-y-6">

    {{-- Banner sambutan --}}
    <section
        class="relative overflow-hidden rounded-2xl border border-sky-100 bg-gradient-to-r from-sky-50 via-blue-50 to-sky-100 shadow-sm">
        <div class="relative z-10 flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
            <div class="flex items-start gap-4">
                <span
                    class="grid h-14 w-14 shrink-0 place-items-center rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-600/25">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M3.75 4.5h16.5M4.5 19.5h15M6.75 16.5V9m5.25 7.5V6m5.25 10.5v-5.25" />
                    </svg>
                </span>

                <div>
                    <p class="text-sm font-medium text-blue-700">Selamat datang, {{ $user->name }}</p>
                    <h1 class="mt-1 text-[26px] font-bold leading-tight tracking-tight text-slate-900 sm:text-[30px]">
                        Dashboard Change Manager
                    </h1>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ $now->translatedFormat('l, j F Y') }} pukul {{ $now->format('H:i') }} WIB
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Tautan pintas --}}
    <section aria-labelledby="tautan-pintas-change-manager">
        <h2 id="tautan-pintas-change-manager" class="sr-only">Tautan pintas</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <a href="{{ route('change-requests.index') }}" wire:navigate
                class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-4">
                    <span
                        class="grid h-11 w-11 place-items-center rounded-xl bg-blue-50 text-blue-600 transition group-hover:bg-blue-600 group-hover:text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M8.25 6.75h9M8.25 12h9M8.25 17.25h9M4.5 6.75h.008v.008H4.5V6.75Zm0 5.25h.008v.008H4.5V12Zm0 5.25h.008v.008H4.5v-.008Z" />
                        </svg>
                    </span>

                    <svg class="h-5 w-5 text-slate-300 transition group-hover:text-blue-500" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                </div>

                <h3 class="mt-4 text-base font-semibold text-slate-800">Daftar RFC</h3>
                <p class="mt-1 text-sm leading-relaxed text-slate-500">
                    Lihat seluruh permintaan perubahan yang tersedia.
                </p>
            </a>
        </div>
    </section>
</div>
