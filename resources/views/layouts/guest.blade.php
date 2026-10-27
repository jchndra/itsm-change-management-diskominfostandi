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

<body class="min-h-screen bg-white font-sans text-slate-900 antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[56%_44%] xl:grid-cols-[58%_42%]">

        {{-- Hero ringkas untuk mobile --}}
        <section class="relative h-[320px] overflow-hidden sm:h-[360px] lg:hidden">
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat"
                style="background-image: url('{{ asset('images/gdgbks.jpg') }}');"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/35 to-slate-950/10"></div>

            <div class="absolute inset-x-0 bottom-0 px-6 pb-8 sm:px-10">
                <div class="h-[3px] w-10 bg-amber-300"></div>
                <p class="mt-4 text-[10px] font-bold uppercase tracking-[0.2em] text-white/90 sm:text-[11px]">
                    Sistem Manajemen Perubahan Layanan TI
                </p>
                <h1 class="mt-2 max-w-md text-[28px] font-extrabold leading-[1.1] tracking-tight text-white sm:text-3xl">
                    Perubahan terencana,<br>layanan terjaga.
                </h1>
            </div>
        </section>

        {{-- Visual utama (desktop) --}}
        <aside class="relative hidden min-h-screen overflow-hidden bg-slate-950 lg:block"
            style="clip-path: polygon(0 0, 100% 0, calc(100% - 90px) 100%, 0 100%);">
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat"
                style="background-image: url('{{ asset('images/gdgbks.jpg') }}');"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/25 to-slate-950/10"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/35 via-transparent to-transparent"></div>

            <div class="absolute inset-x-0 bottom-0 max-w-3xl p-10 pb-12 xl:p-14 xl:pb-16">
                <div class="h-[3px] w-14 bg-amber-300"></div>
                <p class="mt-6 text-[14px] font-bold uppercase tracking-[0.18em] text-white/90">
                    Sistem Manajemen Perubahan Layanan TI
                </p>
                <h1 class="mt-4 text-[46px] font-bold leading-[1.08] tracking-tight text-white xl:text-[60px]">
                    Perubahan terencana,<br>layanan terjaga.
                </h1>
            </div>
        </aside>

        {{-- Panel form --}}
        <main class="flex min-h-screen flex-col bg-white px-6 py-9 sm:px-10 lg:px-8 lg:py-14 xl:px-20">
            <div class="flex w-full max-w-[570px] flex-1 flex-col">

                <div class="flex items-center justify-between">
                    <img src="{{ asset('images/logo-diskominfostandi.png') }}"
                        alt="Logo Diskominfostandi Kota Bekasi" class="h-auto w-32 object-contain sm:w-36">
                </div>

                <div class="mt-12 flex-1 sm:mt-16 lg:mt-14">
                    {{ $slot }}
                </div>

                <footer class="pt-10">
                    <p class="text-[13px] text-slate-400 sm:text-sm">
                        &copy; {{ date('Y') }} Diskominfostandi Kota Bekasi
                    </p>
                </footer>
            </div>
        </main>
    </div>

    @livewireScripts
</body>

</html>
