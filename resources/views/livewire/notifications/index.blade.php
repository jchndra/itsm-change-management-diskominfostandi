<div class="space-y-6">

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-[26px] font-bold leading-tight tracking-tight text-slate-900 sm:text-[30px]">Notifikasi</h1>
            <p class="mt-1 text-sm text-slate-500">Semua pemberitahuan terkait permintaan perubahan Anda.</p>
        </div>

        @if ($unreadCount > 0)
            <button type="button" wire:click="markAllAsRead"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:text-blue-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7" />
                </svg>
                Tandai semua dibaca
            </button>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex rounded-xl bg-slate-100 p-1">
                @foreach (['all' => 'Semua', 'unread' => 'Belum Dibaca', 'read' => 'Sudah Dibaca'] as $key => $label)
                    <button type="button" wire:click="setFilter('{{ $key }}')"
                        @class([
                            'rounded-lg px-3.5 py-1.5 text-xs font-semibold transition',
                            'bg-white text-slate-800 shadow-sm' => $filter === $key,
                            'text-slate-500 hover:text-slate-700' => $filter !== $key,
                        ])>
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <p class="text-xs text-slate-500">
                {{ $totalCount }} notifikasi &middot;
                <span class="font-semibold text-rose-600">{{ $unreadCount }} belum dibaca</span>
            </p>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($notifications as $notification)
                @php
                    $isUnread = is_null($notification->read_at);
                @endphp

                <button type="button" wire:key="notification-{{ $notification->id }}"
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="flex w-full items-start gap-4 px-5 py-4 text-left transition hover:bg-slate-50 {{ $isUnread ? 'bg-blue-50/40' : '' }}">
                    <x-notification-icon :status="data_get($notification->data, 'status')" class="h-9 w-9" />

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-slate-800">
                                {{ data_get($notification->data, 'rfc_number', 'Notifikasi') }}
                            </p>
                            @if ($isUnread)
                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-600">
                            {{ data_get($notification->data, 'message', 'Pesan notifikasi') }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $notification->created_at?->copy()->locale('id')->translatedFormat('d M Y, H:i') }}
                            &middot;
                            {{ $notification->created_at?->copy()->locale('id')->diffForHumans() }}
                        </p>
                    </div>

                    <svg class="mt-2 h-4 w-4 shrink-0 text-slate-300" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                </button>
            @empty
                <div class="px-5 py-16 text-center">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-700">Tidak ada notifikasi</p>
                    <p class="mt-1 text-xs text-slate-500">Notifikasi akan muncul saat status RFC Anda berubah.</p>
                </div>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
