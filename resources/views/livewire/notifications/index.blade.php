<div class="space-y-6">

    <x-ui.page-header eyebrow="Pemberitahuan" title="Notifikasi"
        description="Diskusi RFC dan pemberitahuan lain terkait permintaan perubahan Anda." icon-tone="blue">
        <x-slot:icon>
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
        </x-slot:icon>

        <x-slot:meta>
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-semibold text-slate-600">
                {{ $totalCount }} notifikasi
            </span>
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-[11px] font-semibold text-rose-700">
                <span class="h-1.5 w-1.5 rounded-full bg-rose-500" aria-hidden="true"></span>
                {{ $unreadCount }} belum dibaca
            </span>
        </x-slot:meta>

        @if ($unreadCount > 0)
            <x-slot:actions>
                <x-ui.button variant="secondary" wire:click="markAllAsRead">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m5 13 4 4L19 7" />
                    </svg>
                    Tandai semua dibaca
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @php
        $sections = [
            [
                'key' => 'diskusi',
                'title' => 'Diskusi RFC',
                'description' => 'Komentar dan balasan pada RFC Anda.',
                'items' => $discussionNotifications,
                'filter' => $discussionFilter,
                'total' => $discussionTotalCount,
                'unread' => $discussionUnreadCount,
                'filterMethod' => 'setDiscussionFilter',
                'markAllMethod' => 'markDiscussionAsRead',
                'emptyTitle' => 'Belum ada diskusi',
                'emptyDescription' => 'Komentar pada RFC Anda akan muncul di sini.',
                'icon' => 'M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z',
            ],
            [
                'key' => 'lainnya',
                'title' => 'Notifikasi lainnya',
                'description' => 'Perubahan status, pengingat, dan pemberitahuan lain.',
                'items' => $otherNotifications,
                'filter' => $otherFilter,
                'total' => $otherTotalCount,
                'unread' => $otherUnreadCount,
                'filterMethod' => 'setOtherFilter',
                'markAllMethod' => 'markOtherAsRead',
                'emptyTitle' => 'Tidak ada notifikasi lain',
                'emptyDescription' => 'Notifikasi akan muncul saat status RFC Anda berubah.',
                'icon' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
            ],
        ];
    @endphp

    @foreach ($sections as $section)
        <x-dashboard.panel :title="$section['title']" :description="$section['description']">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                        d="{{ $section['icon'] }}" />
                </svg>
            </x-slot:icon>

            <x-slot:actions>
                <span class="text-xs text-slate-500">
                    {{ $section['total'] }} notifikasi &middot;
                    <span class="font-semibold text-rose-600">{{ $section['unread'] }} belum dibaca</span>
                </span>

                @if ($section['unread'] > 0)
                    <x-ui.button variant="secondary" size="sm" wire:click="{{ $section['markAllMethod'] }}">
                        Tandai bagian ini dibaca
                    </x-ui.button>
                @endif
            </x-slot:actions>

            <div class="border-b border-slate-100 px-5 py-3">
                <div class="inline-flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 ring-1 ring-inset ring-slate-200"
                    role="tablist" aria-label="Saring notifikasi {{ $section['title'] }}">
                    @foreach (['all' => 'Semua', 'unread' => 'Belum Dibaca', 'read' => 'Sudah Dibaca'] as $key => $label)
                        <button type="button" wire:click="{{ $section['filterMethod'] }}('{{ $key }}')"
                            role="tab"
                            aria-selected="{{ $section['filter'] === $key ? 'true' : 'false' }}"
                            @class([
                                'rounded-lg px-3.5 py-1.5 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2',
                                'bg-white text-slate-800 shadow-sm' => $section['filter'] === $key,
                                'text-slate-500 hover:text-slate-700' => $section['filter'] !== $key,
                            ])>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($section['items'] as $notification)
                    @php
                        $isUnread = is_null($notification->read_at);
                    @endphp

                    <button type="button" wire:key="{{ $section['key'] }}-notification-{{ $notification->id }}"
                        wire:click="markAsRead('{{ $notification->id }}')"
                        class="group flex w-full items-start gap-4 px-5 py-4 text-left transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-600 {{ $isUnread ? 'bg-blue-50/40' : '' }}">
                        <x-notification-icon :status="data_get($notification->data, 'status')" :outcome="data_get($notification->data, 'outcome')"
                            :event="data_get($notification->data, 'event')" class="h-9 w-9 shrink-0" />

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ data_get($notification->data, 'rfc_number') ?: 'Notifikasi' }}
                                </p>
                                @if ($isUnread)
                                    <span class="h-2 w-2 rounded-full bg-blue-500" aria-label="Belum dibaca"></span>
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

                        <svg class="mt-2 h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-400"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                        </svg>
                    </button>
                @empty
                    <div class="px-5 py-6">
                        <x-dashboard.empty-state :title="$section['emptyTitle']"
                            :description="$section['emptyDescription']" />
                    </div>
                @endforelse
            </div>

            @if ($section['items']->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $section['items']->links() }}
                </div>
            @endif
        </x-dashboard.panel>
    @endforeach
</div>
