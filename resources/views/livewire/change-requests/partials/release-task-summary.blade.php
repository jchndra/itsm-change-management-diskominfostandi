@if ($isStaff || $isRequesterOwner)
    <div id="progres-tim-rilis" class="scroll-mt-24">
        <x-dashboard.panel title="Progres Tim Rilis"
            description="Status pengerjaan tugas yang didelegasikan kepada Tim Rilis.">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                        d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
            </x-slot:icon>

            <div class="p-5 sm:p-6">
                @if ($rfc->releaseTasks->isEmpty())
                    <p class="text-sm text-slate-500">Belum didisposisikan ke Tim Rilis.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($rfc->releaseTasks as $task)
                            @php
                                $taskStatus = match ($task->execution_status) {
                                    'pending' => ['label' => 'Menunggu dimulai', 'classes' => 'bg-slate-100 text-slate-700'],
                                    'in_progress' => ['label' => 'Sedang dikerjakan', 'classes' => 'bg-blue-100 text-blue-700'],
                                    'successful' => ['label' => 'Selesai', 'classes' => 'bg-emerald-100 text-emerald-700'],
                                    'rolled_back' => ['label' => 'Belum Berhasil', 'classes' => 'bg-amber-100 text-amber-800'],
                                    'cancelled' => ['label' => 'Dibatalkan', 'classes' => 'bg-slate-100 text-slate-500'],
                                    default => ['label' => $task->execution_status, 'classes' => 'bg-slate-100 text-slate-700'],
                                };
                                $isOverdue = $task->execution_status === 'in_progress'
                                    && $task->scheduled_end !== null
                                    && now()->greaterThan($task->scheduled_end);
                                $hasRevision = $task->updates->contains('type', 'estimate_revised');
                            @endphp

                            <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4"
                                wire:key="readonly-release-task-{{ $task->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ $task->releaseTeamMember?->name ?? 'Tim Rilis' }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-600">
                                            {{ 'Estimasi: '.($task->estimated_days ? $task->estimated_days.' hari' : 'belum ditetapkan') }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $taskStatus['classes'] }}">
                                            {{ $taskStatus['label'] }}
                                        </span>
                                        @if ($hasRevision)
                                            <span
                                                class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                                Estimasi direvisi
                                            </span>
                                        @endif
                                        @if ($isOverdue)
                                            <span
                                                class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">
                                                Melewati estimasi
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                @if ($task->scheduled_start !== null && $task->scheduled_end !== null)
                                    <p class="mt-2 text-xs text-slate-600">
                                        Target selesai:
                                        <span class="font-semibold text-slate-800">
                                            {{ $task->scheduled_end->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') }}
                                            WIB
                                        </span>
                                    </p>
                                @endif

                                @if ($task->riskAnalysis !== null)
                                    <div class="mt-3 rounded-xl border border-emerald-100 bg-white p-3">
                                        <p
                                            class="text-xs font-semibold uppercase tracking-wider text-emerald-700">
                                            Analisis risiko</p>
                                        <div
                                            class="mt-2 grid grid-cols-1 gap-2 text-xs text-slate-600 sm:grid-cols-2">
                                            <div>
                                                <p class="font-semibold text-slate-700">Rencana kegiatan</p>
                                                <p class="mt-0.5 whitespace-pre-wrap break-words">
                                                    {{ $task->riskAnalysis->activity_plan }}</p>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-slate-700">Rencana perubahan</p>
                                                <p class="mt-0.5 whitespace-pre-wrap break-words">
                                                    {{ $task->riskAnalysis->change_plan }}</p>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <p class="font-semibold text-slate-700">Mekanisme rollback</p>
                                                <p class="mt-0.5 whitespace-pre-wrap break-words">
                                                    {{ $task->riskAnalysis->rollback_mechanism }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if ($task->updates->isNotEmpty())
                                    <ol class="mt-3 space-y-2 border-t border-slate-200 pt-3">
                                        @foreach ($task->updates as $update)
                                            @php
                                                $updateLabel = match ($update->type) {
                                                    'started' => 'Pengerjaan dimulai',
                                                    'progress' => 'Rencana perubahan',
                                                    'estimate_set' => 'Estimasi ditetapkan',
                                                    'estimate_revised' => 'Update informasi',
                                                    'risk_analysis_submitted' => 'Analisis risiko disimpan',
                                                    'blocked' => 'Kendala',
                                                    default => $update->type,
                                                };
                                            @endphp
                                            <li wire:key="readonly-release-update-{{ $update->id }}"
                                                class="text-xs text-slate-600">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <p class="font-semibold text-slate-700">
                                                        {{ $task->releaseTeamMember?->name ?? 'Tim Rilis' }}
                                                        &middot; {{ $updateLabel }}
                                                    </p>
                                                    <p class="text-xs text-slate-500">
                                                        {{ $update->created_at?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') }}
                                                        WIB
                                                    </p>
                                                </div>
                                                @if ($update->new_estimated_days !== null)
                                                    <p class="mt-1">
                                                        @if ($update->old_estimated_days !== null)
                                                            {{ $update->old_estimated_days }} hari &rarr;
                                                            {{ $update->new_estimated_days }} hari
                                                        @else
                                                            Estimasi: {{ $update->new_estimated_days }} hari
                                                        @endif
                                                    </p>
                                                @endif
                                                @if (filled($update->reason))
                                                    <p class="mt-1">Alasan: {{ $update->reason }}</p>
                                                @endif
                                                @if (filled($update->note))
                                                    <p class="mt-1 whitespace-pre-wrap">{{ $update->note }}</p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-dashboard.panel>
    </div>
@endif
