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
        $canViewAllRfc = $currentUser?->can('rfc.view_all') ?? false;
        $canViewOwnRfc = $currentUser?->can('rfc.view_own') ?? false;
        $isReleaseTeamOnly = ($currentUser?->can('rfc.view_assigned') ?? false)
            && ! $canViewAllRfc
            && ! $canViewOwnRfc;
        $isStaff = $canViewAllRfc;
        $canManageReleaseTeam = $currentUser?->can('release_team.manage') ?? false;
        $unreadCount = $currentUser ? $currentUser->unreadNotifications()->count() : 0;
        $headerDiscussionNotifications = $currentUser
            ? $currentUser->unreadNotifications()->where('data->event', 'comment_added')->take(3)->get()
            : collect();
        $headerOtherNotifications = $currentUser
            ? $currentUser->unreadNotifications()
                ->where(function ($query): void {
                    $query->whereNull('data->event')->orWhere('data->event', '!=', 'comment_added');
                })
                ->take(3)
                ->get()
            : collect();
        $headerDiscussionUnreadCount = $currentUser
            ? $currentUser->unreadNotifications()->where('data->event', 'comment_added')->count()
            : 0;
        $headerOtherUnreadCount = $currentUser
            ? $currentUser->unreadNotifications()
                ->where(function ($query): void {
                    $query->whereNull('data->event')->orWhere('data->event', '!=', 'comment_added');
                })
                ->count()
            : 0;
        $canCreateRfc = $currentUser?->can('rfc.create') ?? false;
        $canReviewCompleteness = $currentUser?->can('rfc.review_completeness') ?? false;
        $canViewDisposition = $currentUser?->can('viewDisposition') ?? false;
        $canViewAdminDashboard = $currentUser?->can('viewAdminDashboard') ?? false;
        $canViewChangeManagerDashboard = $currentUser?->can('viewChangeManagerDashboard') ?? false;
        $isTicketQueueActive = request()->routeIs('change-requests.index')
            && request()->query('queue') === 'incoming';
        $isDashboardActive = $isStaff
            ? false
            : request()->routeIs('requester.dashboard');
        $isReleaseTaskActive = $isReleaseTeamOnly
            && request()->routeIs(['change-requests.index', 'change-requests.show']);
        $isRfcListActive = $isStaff
            && request()->routeIs(['change-requests.index', 'change-requests.show'])
            && ! $isTicketQueueActive;
    @endphp

    <div x-data="{
            sidebarOpen: false,
            mobileSearch: false,
            sidebarCollapsed: (typeof localStorage !== 'undefined' && localStorage.getItem('sidebarCollapsed') === 'true'),
            toggleSidebar() {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                if (typeof localStorage !== 'undefined') {
                    localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed ? 'true' : 'false');
                }
            },
        }"
        x-init="if (typeof localStorage !== 'undefined' && localStorage.getItem('sidebarCollapsed') === null) { sidebarCollapsed = false; }"
        class="min-h-screen">

        {{-- Overlay sidebar mobile --}}
        <div x-show="sidebarOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden"
            x-on:click="sidebarOpen = false"></div>

        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-50 flex -translate-x-full flex-col bg-[#08294a] text-white shadow-2xl transition-all duration-300 lg:translate-x-0"
            :class="[
                sidebarOpen ? 'translate-x-0' : '',
                sidebarCollapsed ? 'w-16' : 'w-64',
            ]">

            {{-- Brand --}}
            <div class="flex items-start gap-3 pb-5 pt-6 transition-all duration-300"
                :class="sidebarCollapsed ? 'px-2 justify-center' : 'px-5'">
                <span class="mt-0.5 grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-white p-1">
                    <img src="{{ asset('images/logo-diskominfostandi.png') }}"
                        alt="Logo Diskominfostandi Kota Bekasi" class="h-full w-full object-contain">
                </span>

                <div class="min-w-0 leading-tight" x-show="!sidebarCollapsed" x-transition.opacity>
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
            <nav class="mt-2 min-h-0 flex-1 space-y-1 overflow-y-auto pb-4 transition-all duration-300"
                :class="sidebarCollapsed ? 'px-2' : 'px-3'">
                @if ($isReleaseTeamOnly)
                    <x-sidebar-link :href="route('release-team.dashboard')" :active="request()->routeIs('release-team.*')">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M4 5.25A1.25 1.25 0 0 1 5.25 4h4.5A1.25 1.25 0 0 1 11 5.25v4.5A1.25 1.25 0 0 1 9.75 11h-4.5A1.25 1.25 0 0 1 4 9.75v-4.5Zm9 0A1.25 1.25 0 0 1 14.25 4h4.5A1.25 1.25 0 0 1 20 5.25v4.5A1.25 1.25 0 0 1 18.75 11h-4.5A1.25 1.25 0 0 1 13 9.75v-4.5Zm-9 9A1.25 1.25 0 0 1 5.25 13h4.5A1.25 1.25 0 0 1 11 14.25v4.5A1.25 1.25 0 0 1 9.75 20h-4.5A1.25 1.25 0 0 1 4 18.75v-4.5Zm9 0A1.25 1.25 0 0 1 14.25 13h4.5A1.25 1.25 0 0 1 20 14.25v4.5A1.25 1.25 0 0 1 18.75 20h-4.5A1.25 1.25 0 0 1 13 18.75v-4.5Z" />
                            </svg>
                        </x-slot:icon>
                        Dashboard
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('change-requests.index')" :active="$isReleaseTaskActive">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </x-slot:icon>
                        Tugas Saya
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('release-calendar.index')" :active="request()->routeIs('release-calendar.*')">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                        </x-slot:icon>
                        Kalender
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
                @else
                @if ($isStaff)
                    @if ($canViewAdminDashboard)
                        <x-sidebar-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                            <x-slot:icon>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M4 5.25A1.25 1.25 0 0 1 5.25 4h4.5A1.25 1.25 0 0 1 11 5.25v4.5A1.25 1.25 0 0 1 9.75 11h-4.5A1.25 1.25 0 0 1 4 9.75v-4.5Zm9 0A1.25 1.25 0 0 1 14.25 4h4.5A1.25 1.25 0 0 1 20 5.25v4.5A1.25 1.25 0 0 1 18.75 11h-4.5A1.25 1.25 0 0 1 13 9.75v-4.5Zm-9 9A1.25 1.25 0 0 1 5.25 13h4.5A1.25 1.25 0 0 1 11 14.25v4.5A1.25 1.25 0 0 1 9.75 20h-4.5A1.25 1.25 0 0 1 4 18.75v-4.5Zm9 0A1.25 1.25 0 0 1 14.25 13h4.5A1.25 1.25 0 0 1 20 14.25v4.5A1.25 1.25 0 0 1 18.75 20h-4.5A1.25 1.25 0 0 1 13 18.75v-4.5Z" />
                                </svg>
                            </x-slot:icon>
                            Dashboard Admin
                        </x-sidebar-link>
                    @endif

                    @if ($canManageReleaseTeam)
                        <x-sidebar-link :href="route('admin.release-team-accounts.index')" :active="request()->routeIs('admin.release-team-accounts.*')">
                            <x-slot:icon>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                            </x-slot:icon>
                            Akun Tim Rilis
                        </x-sidebar-link>
                    @endif

                    @can('users.manage')
                        <x-sidebar-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                            <x-slot:icon>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                            </x-slot:icon>
                            Kelola Pengguna
                        </x-sidebar-link>
                    @endcan

                    @if ($canViewChangeManagerDashboard)
                        <x-sidebar-link :href="route('change-manager.dashboard')" :active="request()->routeIs('change-manager.dashboard')">
                            <x-slot:icon>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M3.75 4.5h16.5M4.5 19.5h15M6.75 16.5V9m5.25 7.5V6m5.25 10.5v-5.25" />
                                </svg>
                            </x-slot:icon>
                            Dashboard
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('release-calendar.index')" :active="request()->routeIs('release-calendar.*')">
                            <x-slot:icon>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                            </x-slot:icon>
                            Kalender
                        </x-sidebar-link>
                    @endif

                    <x-sidebar-link :href="route('change-requests.index')" :active="$isRfcListActive">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M8.25 6.75h9M8.25 12h9M8.25 17.25h9M4.5 6.75h.008v.008H4.5V6.75Zm0 5.25h.008v.008H4.5V12Zm0 5.25h.008v.008H4.5v-.008Z" />
                            </svg>
                        </x-slot:icon>
                        Daftar RFC
                    </x-sidebar-link>

                    @if ($canViewDisposition)
                        <x-sidebar-link :href="route('disposition.index')" :active="request()->routeIs('disposition.*')">
                            <x-slot:icon>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M6 12 3.27 3.125a59.769 59.769 0 0 1 21.485 9.375 59.769 59.769 0 0 1-21.485 9.375L6 12Zm0 0h8.25" />
                                </svg>
                            </x-slot:icon>
                            Disposisi RFC
                        </x-sidebar-link>
                    @endif
                @else
                    <x-sidebar-link :href="route('requester.dashboard')" :active="$isDashboardActive">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M3 10.5 12 3l9 7.5M5.25 9.75V20.25h5.25v-5.25h3v5.25h5.25V9.75" />
                            </svg>
                        </x-slot:icon>
                        Dashboard
                    </x-sidebar-link>
                @endif

                @if ($canReviewCompleteness)
                    <x-sidebar-link :href="route('change-requests.index', ['queue' => 'incoming'])">
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
                    <x-sidebar-link :href="route('change-requests.index')" :active="request()->routeIs('change-requests.*') && !request()->routeIs('change-requests.create') && !request()->routeIs('change-requests.deletions.*')">
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

                <x-sidebar-link :href="route('profile.show')" :active="request()->routeIs('profile.*')">
                    <x-slot:icon>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a8.25 8.25 0 0 1 15 0" />
                        </svg>
                    </x-slot:icon>
                    Profil
                </x-sidebar-link>
                @endif
            </nav>

            {{-- Ilustrasi kota --}}
            <div class="relative mt-auto shrink-0 overflow-hidden" aria-hidden="true">
                <svg viewBox="0 0 256 140" fill="none" class="relative block w-full select-none">
                    {{-- Lapis belakang --}}
                    <g class="text-sky-300/10" fill="currentColor">
                        <rect x="4" y="62" width="18" height="78" />
                        <rect x="26" y="44" width="14" height="96" />
                        <rect x="44" y="70" width="22" height="70" />
                        <rect x="70" y="34" width="16" height="106" />
                        <rect x="77" y="24" width="2" height="10" />
                        <rect x="90" y="56" width="12" height="84" />
                        <rect x="106" y="48" width="20" height="92" />
                        <rect x="130" y="66" width="14" height="74" />
                        <rect x="148" y="30" width="20" height="110" />
                        <rect x="157" y="20" width="2" height="10" />
                        <rect x="172" y="52" width="14" height="88" />
                        <rect x="190" y="40" width="18" height="100" />
                        <rect x="212" y="64" width="18" height="76" />
                        <rect x="234" y="50" width="18" height="90" />
                    </g>

                    {{-- Lapis tengah --}}
                    <g class="text-sky-200/20" fill="currentColor">
                        <rect x="-4" y="88" width="22" height="52" />
                        <rect x="22" y="76" width="16" height="64" />
                        <rect x="42" y="96" width="26" height="44" />
                        <rect x="72" y="82" width="16" height="58" />
                        <rect x="92" y="98" width="14" height="42" />
                        <rect x="110" y="78" width="20" height="62" />
                        <rect x="134" y="92" width="16" height="48" />
                        <rect x="154" y="72" width="20" height="68" />
                        <rect x="178" y="94" width="14" height="46" />
                        <rect x="196" y="84" width="18" height="56" />
                        <rect x="218" y="98" width="16" height="42" />
                        <rect x="238" y="80" width="20" height="60" />
                    </g>

                    {{-- Lapis depan + landmark kubah --}}
                    <g class="text-sky-100/30" fill="currentColor">
                        <rect x="0" y="108" width="24" height="32" />
                        <rect x="28" y="100" width="14" height="40" />
                        <rect x="46" y="114" width="26" height="26" />
                        <rect x="76" y="104" width="16" height="36" />
                        <rect x="96" y="116" width="14" height="24" />
                        <rect x="114" y="102" width="22" height="38" />
                        <rect x="142" y="106" width="24" height="34" />
                        <path d="M154 84c7 10 11 15 11 22h-22c0-7 4-12 11-22Z" />
                        <rect x="153" y="78" width="2" height="6" />
                        <rect x="172" y="110" width="16" height="30" />
                        <rect x="192" y="98" width="20" height="42" />
                        <rect x="216" y="112" width="14" height="28" />
                        <rect x="234" y="104" width="22" height="36" />
                    </g>

                    {{-- Lampu jendela --}}
                    <g class="text-white/40" fill="currentColor">
                        <rect x="4" y="114" width="4" height="4" />
                        <rect x="14" y="114" width="4" height="4" />
                        <rect x="4" y="124" width="4" height="4" />
                        <rect x="14" y="124" width="4" height="4" />
                        <rect x="32" y="108" width="3" height="4" />
                        <rect x="32" y="118" width="3" height="4" />
                        <rect x="80" y="110" width="4" height="4" />
                        <rect x="80" y="120" width="4" height="4" />
                        <rect x="118" y="110" width="4" height="4" />
                        <rect x="126" y="110" width="4" height="4" />
                        <rect x="118" y="120" width="4" height="4" />
                        <rect x="126" y="120" width="4" height="4" />
                        <rect x="148" y="114" width="4" height="4" />
                        <rect x="156" y="114" width="4" height="4" />
                        <rect x="148" y="124" width="4" height="4" />
                        <rect x="156" y="124" width="4" height="4" />
                        <rect x="196" y="106" width="4" height="4" />
                        <rect x="204" y="106" width="4" height="4" />
                        <rect x="196" y="118" width="4" height="4" />
                        <rect x="204" y="118" width="4" height="4" />
                        <rect x="220" y="120" width="3" height="4" />
                        <rect x="240" y="112" width="4" height="4" />
                        <rect x="248" y="112" width="4" height="4" />
                        <rect x="240" y="124" width="4" height="4" />
                        <rect x="248" y="124" width="4" height="4" />
                    </g>
                </svg>
            </div>
        </aside>

        {{-- Area konten --}}
        <div class="flex min-h-screen flex-col transition-[padding] duration-300"
            :class="sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-64'">

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

                    <button type="button" x-on:click="toggleSidebar()"
                        class="hidden items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 lg:inline-flex"
                        :aria-label="sidebarCollapsed ? 'Perluas sidebar' : 'Lipat sidebar'"
                        :title="sidebarCollapsed ? 'Perluas sidebar' : 'Lipat sidebar'">
                        <svg class="h-4 w-4 transition-transform duration-300"
                            :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 19l-7-7 7-7" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition.opacity>Lipat menu</span>
                        <span x-show="sidebarCollapsed" x-transition.opacity>Perluas menu</span>
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
                                aria-label="Cari RFC, nomor tiket, atau kata kunci"
                                class="h-11 w-full rounded-full border border-slate-200 bg-slate-100/70 pl-11 pr-12 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100">
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
                        <div class="relative"
                            x-data="{
                                count: {{ $unreadCount }},
                                open: false,
                                pollingInterval: null,
                                visibilityHandler: null,
                                init() {
                                    this.pollingInterval = setInterval(() => this.syncUnreadCount(), 30000);
                                    this.visibilityHandler = () => {
                                        if (! document.hidden) {
                                            this.syncUnreadCount();
                                        }
                                    };
                                    document.addEventListener('visibilitychange', this.visibilityHandler);
                                },
                                destroy() {
                                    clearInterval(this.pollingInterval);
                                    document.removeEventListener('visibilitychange', this.visibilityHandler);
                                },
                                async syncUnreadCount() {
                                    if (document.hidden) {
                                        return;
                                    }

                                    try {
                                        const response = await fetch('{{ route('notifications.unread-count') }}', {
                                            headers: { Accept: 'application/json' },
                                        });

                                        if (! response.ok) {
                                            return;
                                        }

                                        const payload = await response.json();

                                        if (Number.isFinite(payload.count)) {
                                            this.count = payload.count;
                                        }
                                    } catch (error) {
                                        // Abaikan error jaringan.
                                    }
                                },
                            }" x-on:click.outside="open = false">
                            <button type="button" x-on:click="open = !open"
                                class="relative grid h-10 w-10 place-items-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                aria-label="Notifikasi">
                                <svg class="h-[22px] w-[22px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    :class="{ 'bell-ring': count > 0 && !open }">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                </svg>
                                <span x-show="count > 0" x-text="count" x-cloak data-notification-badge
                                    class="absolute right-0 top-0 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white">
                                    {{ $unreadCount }}
                                </span>
                            </button>

                            <div x-show="open" x-cloak x-transition.origin.top.right
                                class="absolute right-0 z-50 mt-3 w-[320px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10">
                                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                    <p class="text-sm font-bold text-slate-800">Notifikasi</p>
                                    <a href="{{ route('notifications.index') }}" wire:navigate
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-700">Lihat semua</a>
                                </div>
                                <div class="max-h-80 overflow-y-auto p-2">
                                    @if ($headerDiscussionNotifications->isEmpty() && $headerOtherNotifications->isEmpty())
                                        <p class="px-3 py-8 text-center text-xs text-slate-500">Belum ada notifikasi baru.</p>
                                    @endif

                                    @if ($headerDiscussionNotifications->isNotEmpty())
                                        <div class="px-2 pb-1 pt-1.5">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                                    Diskusi RFC
                                                </p>
                                                <span class="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">
                                                    {{ $headerDiscussionUnreadCount }}
                                                </span>
                                            </div>
                                        </div>
                                        @foreach ($headerDiscussionNotifications as $notification)
                                            <a href="{{ route('notifications.index', ['open' => $notification->id]) }}"
                                                class="flex gap-3 rounded-xl p-2.5 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-600">
                                                <x-notification-icon :status="data_get($notification->data, 'status')"
                                                    :outcome="data_get($notification->data, 'outcome')"
                                                    :event="data_get($notification->data, 'event')" class="h-8 w-8" />
                                                <div class="min-w-0">
                                                    <p class="line-clamp-2 text-xs leading-relaxed text-slate-700">
                                                        {{ data_get($notification->data, 'message', 'Notifikasi baru') }}</p>
                                                    <p class="mt-1 text-[10px] text-slate-400">
                                                        {{ $notification->created_at?->copy()->locale('id')->diffForHumans() }}</p>
                                                </div>
                                            </a>
                                        @endforeach
                                    @endif

                                    @if ($headerOtherNotifications->isNotEmpty())
                                        <div class="px-2 pb-1 pt-2">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                                    Notifikasi lainnya
                                                </p>
                                                <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">
                                                    {{ $headerOtherUnreadCount }}
                                                </span>
                                            </div>
                                        </div>
                                        @foreach ($headerOtherNotifications as $notification)
                                            <a href="{{ route('notifications.index', ['open' => $notification->id]) }}"
                                                class="flex gap-3 rounded-xl p-2.5 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-600">
                                                <x-notification-icon :status="data_get($notification->data, 'status')"
                                                    :outcome="data_get($notification->data, 'outcome')"
                                                    :event="data_get($notification->data, 'event')" class="h-8 w-8" />
                                                <div class="min-w-0">
                                                    <p class="line-clamp-2 text-xs leading-relaxed text-slate-700">
                                                        {{ data_get($notification->data, 'message', 'Notifikasi baru') }}</p>
                                                    <p class="mt-1 text-[10px] text-slate-400">
                                                        {{ $notification->created_at?->copy()->locale('id')->diffForHumans() }}</p>
                                                </div>
                                            </a>
                                        @endforeach
                                    @endif
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
