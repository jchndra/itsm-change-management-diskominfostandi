<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'PARAF') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen bg-[#f4f7fb] font-sans text-slate-900 antialiased">
    <x-flash-toast />

    @php
        $currentUser = auth()->user();
        $roleName = $currentUser?->getRoleNames()->first() ?? 'Pengguna';
        $isStaff = $currentUser?->isStaff() ?? false;
        $unreadCount = $currentUser ? $currentUser->unreadNotifications()->count() : 0;
        $headerNotifications = $currentUser ? $currentUser->unreadNotifications()->take(5)->get() : collect();
        $canCreateRfc = $currentUser?->can('rfc.create') ?? false;
        $canReviewCompleteness = $currentUser?->can('rfc.review_completeness') ?? false;
        $isTicketQueueActive = request()->routeIs('change-requests.index')
            && request()->query('queue') === 'incoming';
        $isDashboardActive = $isStaff
            ? request()->routeIs('change-requests.index') && ! $isTicketQueueActive
            : request()->routeIs('requester.dashboard');
    @endphp

    <div x-data="{ sidebarOpen: false, mobileSearch: false }" class="min-h-screen">

        {{-- Overlay sidebar mobile --}}
        <div x-show="sidebarOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden"
            x-on:click="sidebarOpen = false"></div>

        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-[#08294a] text-white shadow-2xl transition-transform duration-300 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : ''">

            {{-- Brand --}}
            <div class="flex items-start gap-3 px-5 pb-5 pt-6">
                <span class="mt-0.5 grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-white p-1">
                    <img src="{{ asset('images/logo-diskominfostandi.png') }}"
                        alt="Logo Diskominfostandi Kota Bekasi" class="h-full w-full object-contain">
                </span>

                <div class="min-w-0 leading-tight">
                    <p class="text-[13px] font-semibold text-blue-100/90">Sistem Manajemen</p>
                    <p class="text-[15px] font-bold leading-snug text-white">Perubahan Layanan TI</p>
                    <p class="mt-1 text-[10px] font-medium uppercase leading-relaxed tracking-[0.14em] text-blue-200/60">
                        Diskominfostandi<br>Kota Bekasi
                    </p>
                </div>

                <button type="button" x-on:click="sidebarOpen = false"
                    class="ml-auto grid h-8 w-8 place-items-center rounded-lg text-blue-100/70 hover:bg-white/10 hover:text-white lg:hidden"
                    aria-label="Tutup menu">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Navigasi --}}
            <nav class="mt-2 flex-1 space-y-1 overflow-y-auto px-3 pb-4">
                <x-sidebar-link :href="$isStaff ? route('change-requests.index') : route('requester.dashboard')" :active="$isDashboardActive">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M3 10.5 12 3l9 7.5M5.25 9.75V20.25h5.25v-5.25h3v5.25h5.25V9.75" />
                        </svg>
                    </x-slot:icon>
                    Dashboard
                </x-sidebar-link>

                @if ($canReviewCompleteness)
                    <x-sidebar-link :href="route('change-requests.index', ['queue' => 'incoming'])" :active="$isTicketQueueActive">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v6a2.25 2.25 0 0 1-2.25 2.25H12M8.25 9H6.75A2.25 2.25 0 0 0 4.5 11.25v6A2.25 2.25 0 0 0 6.75 19.5h6a2.25 2.25 0 0 0 2.25-2.25V15" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="m9 12.75 2.25 2.25 4.5-4.5" />
                            </svg>
                        </x-slot:icon>
                        Buat Tiket Change
                    </x-sidebar-link>
                @endif

                @if ($canCreateRfc)
                    <x-sidebar-link :href="route('change-requests.create')" :active="request()->routeIs('change-requests.create')">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M14 3v4a1 1 0 0 0 1 1h4M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M9 13h6M12 10v6" />
                            </svg>
                        </x-slot:icon>
                        Ajukan RFC
                    </x-sidebar-link>
                @endif

                @unless ($isStaff)
                    <x-sidebar-link :href="route('change-requests.index')" :active="request()->routeIs('change-requests.*') && !request()->routeIs('change-requests.create')">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M14 3v4a1 1 0 0 0 1 1h4M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M9 12h6M9 16h4" />
                            </svg>
                        </x-slot:icon>
                        RFC Saya
                    </x-sidebar-link>
                @endunless

                <x-sidebar-link :href="route('change-requests.deletions.index')" :active="request()->routeIs('change-requests.deletions.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v5M14 11v5" />
                        </svg>
                    </x-slot:icon>
                    Riwayat Penghapusan
                </x-sidebar-link>

                <x-sidebar-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')" :badge="$unreadCount ?: null">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                    </x-slot:icon>
                    Notifikasi
                </x-sidebar-link>

                <x-sidebar-link :href="route('profile.show')" :active="request()->routeIs('profile.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a8.25 8.25 0 0 1 15 0" />
                        </svg>
                    </x-slot:icon>
                    Profil
                </x-sidebar-link>
            </nav>

            {{-- Footer sidebar --}}
            <div class="relative mt-auto overflow-hidden">
                <svg viewBox="0 0 256 120" fill="none" class="pointer-events-none absolute inset-x-0 bottom-0 h-32 w-full text-white/[0.07]"
                    aria-hidden="true">
                    <g fill="currentColor">
                        <rect x="0" y="66" width="22" height="54" />
                        <rect x="26" y="48" width="16" height="72" />
                        <rect x="46" y="74" width="26" height="46" />
                        <rect x="76" y="38" width="20" height="82" />
                        <rect x="100" y="58" width="14" height="62" />
                        <rect x="118" y="28" width="24" height="92" />
                        <rect x="146" y="62" width="18" height="58" />
                        <rect x="168" y="44" width="22" height="76" />
                        <rect x="194" y="70" width="16" height="50" />
                        <rect x="214" y="52" width="20" height="68" />
                        <rect x="238" y="76" width="18" height="44" />
                        <path d="M130 18c5 6 8 9 8 13h-16c0-4 3-7 8-13Z" />
                    </g>
                </svg>

                <div class="relative m-3 rounded-2xl border border-white/10 bg-white/[0.05] p-4 backdrop-blur-sm">
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10 text-blue-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-5h6v5" />
                            </svg>
                        </span>
                        <p class="text-[11px] font-semibold leading-tight text-blue-50">
                            Diskominfostandi<br>Kota Bekasi
                        </p>
                    </div>
                    <p class="mt-3 text-[11px] leading-relaxed text-blue-100/60">
                        Bersama Mewujudkan<br>Layanan TI yang Andal
                    </p>
                </div>
            </div>
        </aside>

        {{-- Area konten --}}
        <div class="flex min-h-screen flex-col lg:pl-64">

            {{-- Topbar --}}
            <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur">
                <div class="flex h-[72px] items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button type="button" x-on:click="sidebarOpen = true"
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-700 lg:hidden"
                        aria-label="Buka menu">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        </svg>
                    </button>

                    {{-- Pencarian desktop --}}
                    <form action="{{ route('change-requests.index') }}" method="GET" class="hidden flex-1 md:block">
                        <div class="relative max-w-xl">
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                                </svg>
                            </span>
                            <input type="search" name="q" value="{{ request('q') }}"
                                placeholder="Cari RFC, nomor tiket, atau kata kunci..."
                                class="h-11 w-full rounded-full border border-slate-200 bg-slate-100/70 pl-11 pr-12 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100">
                            <button type="submit"
                                class="absolute right-2.5 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-full text-slate-400 transition hover:bg-white hover:text-blue-600"
                                aria-label="Cari">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                                </svg>
                            </button>
                        </div>
                    </form>

                    <div class="ml-auto flex items-center gap-1.5 sm:gap-3">
                        {{-- Pencarian mobile --}}
                        <button type="button" x-on:click="mobileSearch = !mobileSearch"
                            class="grid h-10 w-10 place-items-center rounded-full text-slate-500 hover:bg-slate-100 md:hidden"
                            aria-label="Cari">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                            </svg>
                        </button>

                        {{-- Notifikasi --}}
                        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                            <button type="button" x-on:click="open = !open"
                                class="relative grid h-10 w-10 place-items-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                aria-label="Notifikasi">
                                <svg class="h-[22px] w-[22px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                </svg>
                                @if ($unreadCount > 0)
                                    <span data-notification-badge
                                        class="absolute right-0 top-0 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white">
                                        {{ $unreadCount }}
                                    </span>
                                @endif
                            </button>

                            <div x-show="open" x-cloak x-transition.origin.top.right
                                class="absolute right-0 z-50 mt-3 w-[320px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10">
                                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                    <p class="text-sm font-bold text-slate-800">Notifikasi</p>
                                    <a href="{{ route('notifications.index') }}" wire:navigate
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-700">Lihat semua</a>
                                </div>
                                <div class="max-h-80 overflow-y-auto p-2">
                                    @forelse ($headerNotifications as $notification)
                                        <a href="{{ route('notifications.index', ['open' => $notification->id]) }}"
                                            class="flex gap-3 rounded-xl p-2.5 transition hover:bg-slate-50">
                                            <x-notification-icon :status="data_get($notification->data, 'status')"
                                                class="h-8 w-8" />
                                            <div class="min-w-0">
                                                <p class="line-clamp-2 text-xs leading-relaxed text-slate-700">
                                                    {{ data_get($notification->data, 'message', 'Notifikasi baru') }}</p>
                                                <p class="mt-1 text-[10px] text-slate-400">
                                                    {{ $notification->created_at?->copy()->locale('id')->diffForHumans() }}</p>
                                            </div>
                                        </a>
                                    @empty
                                        <p class="px-3 py-8 text-center text-xs text-slate-500">Belum ada notifikasi baru.
                                        </p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="hidden h-8 w-px bg-slate-200 sm:block"></div>

                        {{-- Menu pengguna --}}
                        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                            <button type="button" x-on:click="open = !open"
                                class="flex items-center gap-2.5 rounded-full py-1 pl-1 pr-1.5 transition hover:bg-slate-100 sm:pr-2">
                                <x-user-avatar :name="$currentUser?->name ?? 'Pengguna'" />
                                <span class="hidden text-left sm:block">
                                    <span
                                        class="block max-w-[150px] truncate text-sm font-semibold leading-tight text-slate-800">{{ $currentUser?->name ?? 'Pengguna' }}</span>
                                    <span class="block text-[11px] leading-tight text-slate-500">{{ $roleName }}</span>
                                </span>
                                <svg class="h-4 w-4 text-slate-400 transition" :class="open ? 'rotate-180' : ''"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="m6 9 6 6 6-6" />
                                </svg>
                            </button>

                            <div x-show="open" x-cloak x-transition.origin.top.right
                                class="absolute right-0 z-50 mt-3 w-64 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10">
                                <div class="border-b border-slate-100 px-4 py-3">
                                    <p class="truncate text-sm font-bold text-slate-800">
                                        {{ $currentUser?->name ?? 'Pengguna' }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $currentUser?->email }}</p>
                                </div>
                                <div class="p-1.5">
                                    <a href="{{ route('profile.show') }}" wire:navigate
                                        class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a8.25 8.25 0 0 1 15 0" />
                                        </svg>
                                        Profil Saya
                                    </a>
                                    <a href="{{ route('notifications.index') }}" wire:navigate
                                        class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                        </svg>
                                        Notifikasi
                                        @if ($unreadCount > 0)
                                            <span
                                                class="ml-auto grid h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1.5 text-[10px] font-bold text-white">{{ $unreadCount }}</span>
                                        @endif
                                    </a>
                                </div>
                                <div class="border-t border-slate-100 p-1.5">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit"
                                            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-rose-600 transition hover:bg-rose-50">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                    d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                                            </svg>
                                            Keluar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pencarian mobile --}}
                <div x-show="mobileSearch" x-cloak class="border-t border-slate-100 px-4 py-3 md:hidden">
                    <form action="{{ route('change-requests.index') }}" method="GET" class="relative">
                        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                            </svg>
                        </span>
                        <input type="search" name="q" value="{{ request('q') }}"
                            placeholder="Cari RFC, nomor tiket, atau kata kunci..."
                            class="h-10 w-full rounded-full border border-slate-200 bg-slate-100/70 pl-10 pr-4 text-sm placeholder:text-slate-400 focus:border-blue-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100">
                    </form>
                </div>
            </header>

            {{-- Konten halaman --}}
            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div class="mx-auto w-full max-w-[1400px]">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @livewireScripts
</body>

</html>
