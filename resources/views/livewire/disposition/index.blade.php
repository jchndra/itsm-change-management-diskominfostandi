<div class="space-y-6">
    <x-ui.page-header eyebrow="Manajemen Perubahan" title="Disposisi RFC"
        description="Teruskan RFC yang sudah disetujui kepada anggota Tim Rilis. Disposisi dicatat sebagai tugas dan tidak mengubah status RFC."
        icon-tone="navy">
        <x-slot:icon>
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M6 12 3.27 3.125a59.769 59.769 0 0 1 21.485 9.375 59.769 59.769 0 0 1-21.485 9.375L6 12Zm0 0h8.25" />
            </svg>
        </x-slot:icon>

        <x-slot:meta>
            <span
                class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-semibold text-blue-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M6 12 3.27 3.125a59.769 59.769 0 0 1 21.485 9.375 59.769 59.769 0 0 1-21.485 9.375L6 12Zm0 0h8.25" />
                </svg>
                Hanya Change Manager
            </span>
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                Menunggu
                <span class="tabular-nums">{{ $waitingCount }}</span>
            </span>
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">
                Didisposisi
                <span class="tabular-nums">{{ $assignedCount }}</span>
            </span>
        </x-slot:meta>
    </x-ui.page-header>

    <x-dashboard.panel title="Daftar Disposisi"
        description="Kelola penugasan Tim Rilis atas RFC yang sudah disetujui.">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                    d="M3.75 6.75h16.5m-16.5 0v10.5a2.25 2.25 0 0 0 2.25 2.25h12a2.25 2.25 0 0 0 2.25-2.25V6.75m-16.5 0A2.25 2.25 0 0 1 6 4.5h12a2.25 2.25 0 0 1 2.25 2.25" />
            </svg>
        </x-slot:icon>

        <x-slot:actions>
            <div class="inline-flex rounded-xl bg-slate-100 p-1 ring-1 ring-inset ring-slate-200"
                role="tablist" aria-label="Status disposisi">
                <button type="button" wire:click="setTab('waiting')" role="tab"
                    @class([
                        'rounded-lg px-3.5 py-1.5 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2',
                        'bg-white text-slate-800 shadow-sm' => $tab === 'waiting',
                        'text-slate-500 hover:text-slate-700' => $tab !== 'waiting',
                    ])
                    aria-selected="{{ $tab === 'waiting' ? 'true' : 'false' }}">
                    Menunggu Disposisi
                    <span
                        class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold tabular-nums {{ $tab === 'waiting' ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-600' }}">{{ $waitingCount }}</span>
                </button>
                <button type="button" wire:click="setTab('assigned')" role="tab"
                    @class([
                        'rounded-lg px-3.5 py-1.5 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2',
                        'bg-white text-slate-800 shadow-sm' => $tab === 'assigned',
                        'text-slate-500 hover:text-slate-700' => $tab !== 'assigned',
                    ])
                    aria-selected="{{ $tab === 'assigned' ? 'true' : 'false' }}">
                    Sudah Didisposisi
                    <span
                        class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold tabular-nums {{ $tab === 'assigned' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $assignedCount }}</span>
                </button>
            </div>
        </x-slot:actions>

        @if ($tab === 'waiting')
            <div class="overflow-x-auto">
                <table class="rfc-responsive-table min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-3.5 font-semibold">No. RFC</th>
                            <th scope="col" class="px-5 py-3.5 font-semibold">Judul</th>
                            <th scope="col" class="px-5 py-3.5 font-semibold">Pemohon &amp; Unit Kerja</th>
                            <th scope="col" class="px-5 py-3.5 font-semibold">Kategori Diusulkan</th>
                            <th scope="col" class="px-5 py-3.5 font-semibold">Tanggal Disetujui</th>
                            <th scope="col" class="px-5 py-3.5 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($waitingRfcs as $rfc)
                            @php
                                $approvedAt = $rfc->decisions->first()?->decision_date;
                            @endphp
                            <tr wire:key="waiting-rfc-{{ $rfc->id }}"
                                class="align-top transition hover:bg-blue-50/40">
                                <td data-label="No. RFC"
                                    class="whitespace-nowrap px-5 py-4 font-semibold text-slate-800">
                                    {{ $rfc->displayIdentifier() }}
                                </td>
                                <td data-label="Judul" class="max-w-xs px-5 py-4">
                                    <p class="font-medium leading-snug text-slate-800">{{ $rfc->title }}</p>
                                </td>
                                <td data-label="Pemohon & Unit Kerja" class="px-5 py-4 text-slate-600">
                                    <p class="font-medium text-slate-800">{{ $rfc->submittedBy?->name ?? '-' }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ $rfc->submittedBy?->opd?->name ?? 'Unit kerja belum diisi' }}</p>
                                </td>
                                <td data-label="Kategori Diusulkan"
                                    class="whitespace-nowrap px-5 py-4 text-slate-600">
                                    <x-category-badge :kategori="$rfc->change_type" size="sm"
                                        :label="$rfc->change_type === 'emergency' ? 'Darurat (Emergency)' : null" />
                                </td>
                                <td data-label="Tanggal Disetujui"
                                    class="whitespace-nowrap px-5 py-4 text-slate-600">
                                    {{ filled($approvedAt) ? \Illuminate\Support\Carbon::parse($approvedAt)->locale('id')->translatedFormat('d M Y') : '-' }}
                                </td>
                                <td data-label="Aksi" class="whitespace-nowrap px-5 py-4 text-right">
                                    <x-ui.button :href="route('disposition.assign', $rfc)" navigate size="sm"
                                        class="justify-center">
                                        Disposisi
                                    </x-ui.button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-4">
                                    <x-dashboard.empty-state title="Tidak ada RFC yang menunggu disposisi"
                                        description="RFC yang sudah disetujui dan belum memiliki tugas akan muncul di sini." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($waitingRfcs->hasPages())
                <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-[11px] font-medium text-slate-500">
                        {{ $waitingRfcs->total() }} RFC menunggu disposisi
                    </p>
                    {{ $waitingRfcs->links() }}
                </div>
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="rfc-responsive-table min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-3.5 font-semibold">No. RFC</th>
                            <th scope="col" class="px-5 py-3.5 font-semibold">Judul</th>
                            <th scope="col" class="px-5 py-3.5 font-semibold">Penerima</th>
                            <th scope="col" class="px-5 py-3.5 font-semibold">Tanggal Disposisi</th>
                            <th scope="col" class="px-5 py-3.5 font-semibold">Status Tugas</th>
                            <th scope="col" class="px-5 py-3.5 font-semibold">Status RFC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($assignedRfcs as $rfc)
                            @php
                                $recipientNames = $rfc->releaseTasks
                                    ->map(fn ($task) => $task->releaseTeamMember?->name)
                                    ->filter()
                                    ->unique()
                                    ->values();
                                $dispositionAt = $rfc->releaseTasks->max('created_at');
                                $taskStatuses = $rfc->releaseTasks->pluck('execution_status')->unique()->values();
                                $taskStatusLabels = [
                                    'pending' => 'Menunggu',
                                    'in_progress' => 'Dikerjakan',
                                    'successful' => 'Selesai',
                                    'rolled_back' => 'Reject',
                                ];
                                $taskStatusClasses = [
                                    'pending' => 'bg-amber-100 text-amber-700',
                                    'in_progress' => 'bg-blue-100 text-blue-700',
                                    'successful' => 'bg-emerald-100 text-emerald-700',
                                    'rolled_back' => 'bg-rose-100 text-rose-700',
                                ];
                            @endphp
                            <tr wire:key="assigned-rfc-{{ $rfc->id }}"
                                class="align-top transition hover:bg-blue-50/40">
                                <td data-label="No. RFC"
                                    class="whitespace-nowrap px-5 py-4 font-semibold text-slate-800">
                                    {{ $rfc->displayIdentifier() }}
                                </td>
                                <td data-label="Judul" class="max-w-xs px-5 py-4">
                                    <p class="font-medium leading-snug text-slate-800">{{ $rfc->title }}</p>
                                </td>
                                <td data-label="Penerima" class="px-5 py-4 text-slate-600">
                                    {{ $recipientNames->isNotEmpty() ? $recipientNames->join(', ') : '-' }}
                                </td>
                                <td data-label="Tanggal Disposisi"
                                    class="whitespace-nowrap px-5 py-4 text-slate-600">
                                    {{ filled($dispositionAt) ? \Illuminate\Support\Carbon::parse($dispositionAt)->locale('id')->translatedFormat('d M Y, H:i') : '-' }}
                                </td>
                                <td data-label="Status Tugas" class="px-5 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($taskStatuses as $taskStatus)
                                            <span
                                                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $taskStatusClasses[$taskStatus] ?? 'bg-slate-100 text-slate-600' }}">
                                                {{ $taskStatusLabels[$taskStatus] ?? ucfirst((string) $taskStatus) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td data-label="Status RFC" class="whitespace-nowrap px-5 py-4">
                                    <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-4">
                                    <x-dashboard.empty-state title="Belum ada RFC yang didisposisikan"
                                        description="Disposisi yang sudah dikirim akan tercatat di sini." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($assignedRfcs->hasPages())
                <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-[11px] font-medium text-slate-500">
                        {{ $assignedRfcs->total() }} RFC sudah didisposisikan
                    </p>
                    {{ $assignedRfcs->links() }}
                </div>
            @endif
        @endif
    </x-dashboard.panel>
</div>
