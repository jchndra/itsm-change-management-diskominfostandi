<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Masuk' }} &mdash; {{ config('app.name', 'PARAF') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen overflow-x-hidden bg-[#f7faff] font-sans text-slate-900 antialiased">
    @php($isRegisterPage = request()->routeIs('register'))

    <div class="min-h-screen lg:grid lg:grid-cols-[40%_60%]">

        {{-- Hero ringkas untuk mobile dan tablet --}}
        <section class="relative isolate overflow-hidden bg-[#03142e] lg:hidden">
            <img src="{{ asset('images/gdgbks.jpg') }}" alt="Panorama Kota Bekasi pada malam hari"
                class="absolute inset-0 h-full w-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-t from-[#03142e] via-[#03142e]/55 to-[#03142e]/20"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-[#03142e]/35 via-transparent to-transparent"></div>
            <div class="absolute -right-16 top-8 h-40 w-40 rounded-full border border-white/10"></div>
            <div class="absolute -right-8 top-16 h-24 w-24 rounded-full border border-white/10"></div>

            <div class="relative flex min-h-[225px] flex-col justify-between px-5 py-5 sm:min-h-[265px] sm:px-8 sm:py-7">
                <div class="w-fit rounded-xl bg-white p-2 shadow-lg shadow-slate-950/20 sm:p-2.5">
                    <img src="{{ asset('images/logo-diskominfostandi.png') }}"
                        alt="Logo Diskominfostandi Kota Bekasi" class="h-auto w-28 object-contain sm:w-32">
                </div>

                <div class="max-w-xl pt-9">
                    <div class="h-[3px] w-9 bg-amber-300"></div>
                    <p class="mt-3 text-[9px] font-bold uppercase tracking-[0.18em] text-white/85 sm:text-[10px]">
                        Sistem Manajemen Perubahan Layanan TI
                    </p>
                    <h1 class="mt-2 text-[26px] font-bold leading-[1.12] tracking-[-0.025em] text-white sm:text-[32px]">
                        Perubahan terencana,<br>layanan terjaga.
                    </h1>
                </div>
            </div>
        </section>

        {{-- Panel identitas institusi (desktop) --}}
        <aside
            class="relative hidden overflow-hidden bg-[#03142e] lg:sticky lg:top-0 lg:z-20 lg:flex lg:h-screen lg:w-[calc(100%+3vw)] lg:flex-col"
            style="clip-path: polygon(0 0, 100% 0, calc(100% - 3vw) 100%, 0 100%);">
            <img src="{{ asset('images/gdgbks.jpg') }}" alt="Panorama Kota Bekasi pada malam hari"
                class="absolute inset-0 h-full w-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-t from-[#03142e] via-[#03142e]/50 to-[#03142e]/20"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-[#03142e]/50 via-[#03142e]/10 to-transparent"></div>
            <div class="absolute -right-28 top-[20%] h-72 w-72 rounded-full border border-white/10"></div>
            <div class="absolute -right-16 top-[26%] h-48 w-48 rounded-full border border-white/10"></div>

            <div class="relative z-10 flex h-full flex-col py-8 pl-8 pr-12 xl:py-10 xl:pl-10 xl:pr-14">
                <div class="w-fit rounded-xl bg-white p-2.5 shadow-xl shadow-slate-950/25">
                    <img src="{{ asset('images/logo-diskominfostandi.png') }}"
                        alt="Logo Diskominfostandi Kota Bekasi" class="h-auto w-[150px] object-contain">
                </div>

                <div class="mt-auto max-w-[27rem] pb-1">
                    <div class="h-[3px] w-12 bg-amber-300"></div>
                    <p class="mt-5 text-[10px] font-bold uppercase tracking-[0.18em] text-white/85 xl:text-[11px]">
                        Sistem Manajemen Perubahan Layanan TI
                    </p>
                    <h1
                        class="mt-3 text-[36px] font-bold leading-[1.14] tracking-[-0.03em] text-white xl:text-[40px] 2xl:text-[42px]">
                        Perubahan terencana,<br>layanan terjaga.
                    </h1>
                    <p class="mt-4 max-w-[24rem] text-[14px] leading-[1.7] text-slate-200/90">
                        Bersama mewujudkan layanan teknologi informasi yang lebih andal, aman, dan berkelanjutan di
                        Kota Bekasi.
                    </p>

                    <div class="mt-7 hidden grid-cols-3 gap-4 border-t border-white/15 pt-5 2xl:grid">
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-sky-300" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.5 16.5 17.25 20.25 22.5 12.75M8.25 6.75a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm12 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0ZM8.25 18.75a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm6.75-3-1.5 1.5" />
                            </svg>
                            <span class="text-[10.5px] font-semibold leading-snug text-white/85">Integrasi<br>Layanan TI</span>
                        </div>
                        <div class="flex items-start gap-3 border-l border-white/15 pl-4">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-sky-300" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 19.5a6 6 0 0 0-12 0M9 11.25a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Zm6.75 8.25a4.5 4.5 0 0 0-2.25-3.9m.75-7.35a3 3 0 1 1 0-6 3 3 0 0 1 0 6Zm3 6.75a4.5 4.5 0 0 0-1.5-3.3" />
                            </svg>
                            <span class="text-[10.5px] font-semibold leading-snug text-white/85">Kolaborasi Lintas
                                Perangkat Daerah</span>
                        </div>
                        <div class="flex items-start gap-3 border-l border-white/15 pl-4">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-sky-300" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 3 4.5 6v5.25c0 4.28 2.89 8.25 7.5 9.75 4.61-1.5 7.5-5.47 7.5-9.75V6L12 3Zm-2.25 8.25 1.5 1.5 3-3" />
                            </svg>
                            <span class="text-[10.5px] font-semibold leading-snug text-white/85">Transparansi<br>Proses
                                Perubahan</span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Panel formulir autentikasi --}}
        <main class="relative z-0 flex min-h-screen min-w-0 flex-col bg-[#f7faff]">
            <div
                class="mx-auto flex w-full flex-1 flex-col px-4 py-5 sm:px-8 sm:py-8 lg:px-12 lg:py-9 xl:px-16 xl:py-10">
                <header class="flex min-h-8 items-center justify-end">
                    <p class="text-right text-[13px] text-slate-500 sm:text-sm">
                        {{ $isRegisterPage ? 'Sudah punya akun?' : 'Belum punya akun?' }}
                        <a href="{{ $isRegisterPage ? route('login') : route('register') }}" wire:navigate
                            class="ml-1 rounded-sm font-semibold text-[#1746d1] underline-offset-4 transition hover:text-[#123ab0] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1746d1] focus-visible:ring-offset-4">
                            {{ $isRegisterPage ? 'Masuk di sini' : 'Daftar sebagai Pemohon' }}
                            <span aria-hidden="true" class="ml-0.5">&rarr;</span>
                        </a>
                    </p>
                </header>

                <div class="flex flex-1 items-start justify-center py-7 sm:py-9 lg:py-8">
                    <div class="w-full">
                        {{ $slot }}
                    </div>
                </div>

                <footer
                    class="mt-6 flex flex-col gap-2 border-t border-slate-200/80 pt-5 text-[12px] text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:text-[13px]">
                    <p>&copy; {{ date('Y') }} Diskominfostandi Kota Bekasi</p>
                    <p class="text-slate-400">Portal internal perubahan layanan TI</p>
                </footer>
            </div>
        </main>
    </div>

    @livewireScripts
</body>

</html>
