<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-[26px] font-bold leading-tight tracking-tight text-slate-900 sm:text-[30px]">
                Disposisi RFC
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                Teruskan RFC yang sudah disetujui kepada anggota Tim Rilis. Disposisi dicatat sebagai tugas dan
                tidak mengubah status RFC.
            </p>
        </div>

        <span
            class="inline-flex items-center gap-2 self-start rounded-full bg-blue-50 px-3.5 py-2 text-xs font-semibold text-blue-700 sm:self-auto">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M6 12 3.27 3.125a59.769 59.769 0 0 1 21.485 9.375 59.769 59.769 0 0 1-21.485 9.375L6 12Zm0 0h8.25" />
            </svg>
            Hanya Change Manager
        </span>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <div class="inline-flex rounded-xl bg-slate-100 p-1">
                <button type="button" wire:click="setTab('waiting')"
                    @class([
                        'rounded-lg px-3.5 py-1.5 text-xs font-semibold transition',
                        'bg-white text-slate-800 shadow-sm' => $tab === 'waiting',
                        'text-slate-500 hover:text-slate-700' => $tab !== 'waiting',
                    ])>
                    Menunggu Disposisi
                    <span class="ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] text-slate-600">{{ $waitingCount }}</span>
                </button>
                <button type="button" wire:click="setTab('assigned')"
                    @class([
                        'rounded-lg px-3.5 py-1.5 text-xs font-semibold transition',
                        'bg-white text-slate-800 shadow-sm' => $tab === 'assigned',
                        'text-slate-500 hover:text-slate-700' => $tab !== 'assigned',
                    ])>
                    Sudah Didisposisi
                    <span class="ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] text-slate-600">{{ $assignedCount }}</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            @if ($tab === 'waiting')
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">No. RFC</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Judul</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Pemohon &amp; Unit Kerja</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Kategori Diusulkan</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Tanggal Disetujui</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($waitingRfcs as $rfc)
                            @php
                                $categoryLabel = match ((string) $rfc->change_type) {
                                    'standard' => 'Standar',
                                    'normal' => 'Normal',
                                    'emergency' => 'Darurat (Emergency)',
                                    default => filled($rfc->change_type) ? ucfirst((string) $rfc->change_type) : '-',
                                };
                                $approvedAt = $rfc->decisions->first()?->decision_date;
                            @endphp
                            <tr wire:key="waiting-rfc-{{ $rfc->id }}" class="align-top">
                                <td class="whitespace-nowrap px-5 py-4 font-semibold text-slate-800">
                                    {{ $rfc->displayIdentifier() }}
                                </td>
                                <td class="max-w-xs px-5 py-4">
                                    <p class="font-medium text-slate-800">{{ $rfc->title }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    <p class="font-medium text-slate-800">{{ $rfc->submittedBy?->name ?? '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ $rfc->submittedBy?->opd?->name ?? 'Unit kerja belum diisi' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $categoryLabel }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">
                                    {{ filled($approvedAt) ? \Illuminate\Support\Carbon::parse($approvedAt)->locale('id')->translatedFormat('d M Y') : '-' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('disposition.assign', $rfc) }}" wire:navigate
                                        class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                        Disposisi
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-16 text-center">
                                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400">
                                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                                d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </span>
                                    <p class="mt-3 text-sm font-semibold text-slate-700">Tidak ada RFC yang menunggu disposisi</p>
                                    <p class="mt-1 text-xs text-slate-500">RFC yang sudah disetujui dan belum memiliki tugas akan muncul di sini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">No. RFC</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Judul</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Penerima</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Tanggal Disposisi</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Status Tugas</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Status RFC</th>
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
                                    'rolled_back' => 'Rollback',
                                ];
                                $taskStatusClasses = [
                                    'pending' => 'bg-amber-100 text-amber-700',
                                    'in_progress' => 'bg-blue-100 text-blue-700',
                                    'successful' => 'bg-emerald-100 text-emerald-700',
                                    'rolled_back' => 'bg-rose-100 text-rose-700',
                                ];
                            @endphp
                            <tr wire:key="assigned-rfc-{{ $rfc->id }}" class="align-top">
                                <td class="whitespace-nowrap px-5 py-4 font-semibold text-slate-800">
                                    {{ $rfc->displayIdentifier() }}
                                </td>
                                <td class="max-w-xs px-5 py-4">
                                    <p class="font-medium text-slate-800">{{ $rfc->title }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    {{ $recipientNames->isNotEmpty() ? $recipientNames->join(', ') : '-' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">
                                    {{ filled($dispositionAt) ? \Illuminate\Support\Carbon::parse($dispositionAt)->locale('id')->translatedFormat('d M Y, H:i') : '-' }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($taskStatuses as $taskStatus)
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $taskStatusClasses[$taskStatus] ?? 'bg-slate-100 text-slate-600' }}">
                                                {{ $taskStatusLabels[$taskStatus] ?? ucfirst((string) $taskStatus) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-16 text-center">
                                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400">
                                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                                d="M6 12 3.27 3.125a59.769 59.769 0 0 1 21.485 9.375 59.769 59.769 0 0 1-21.485 9.375L6 12Zm0 0h8.25" />
                                        </svg>
                                    </span>
                                    <p class="mt-3 text-sm font-semibold text-slate-700">Belum ada RFC yang didisposisikan</p>
                                    <p class="mt-1 text-xs text-slate-500">Disposisi yang sudah dikirim akan tercatat di sini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        @if ($tab === 'waiting' && $waitingRfcs->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">
                {{ $waitingRfcs->links() }}
            </div>
        @elseif ($tab === 'assigned' && $assignedRfcs->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">
                {{ $assignedRfcs->links() }}
            </div>
        @endif
    </div>
</div>
