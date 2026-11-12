            @if ($isStaff || $isRequesterOwner)
                <div id="progres-tim-rilis" class="bg-white rounded-xl shadow-sm border border-emerald-100 p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Progres Tim Rilis</h3>

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
                                        'rolled_back' => ['label' => 'Rollback', 'classes' => 'bg-rose-100 text-rose-700'],
                                        default => ['label' => $task->execution_status, 'classes' => 'bg-slate-100 text-slate-700'],
                                    };
                                    $isOverdue = $task->execution_status === 'in_progress'
                                        && $task->scheduled_end !== null
                                        && now()->greaterThan($task->scheduled_end);
                                    $hasRevision = $task->updates->contains('type', 'estimate_revised');
                                @endphp

                                <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-4"
                                    wire:key="readonly-release-task-{{ $task->id }}">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-800">
                                                {{ $task->releaseTeamMember?->name ?? 'Tim Rilis' }}
                                            </p>
                                            <p class="mt-1 text-xs text-slate-600">
                                                Estimasi: {{ $task->estimated_days ? $task->estimated_days.' hari' : 'belum ditetapkan' }}
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
                                                {{ $task->scheduled_end->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') }} WIB
                                            </span>
                                        </p>
                                    @endif

                                    @if ($task->updates->isNotEmpty())
                                        <ol class="mt-3 space-y-2 border-t border-slate-200 pt-3">
                                            @foreach ($task->updates as $update)
                                                @php
                                                    $updateLabel = match ($update->type) {
                                                        'started' => 'Pengerjaan dimulai',
                                                        'progress' => 'Catatan kegiatan',
                                                        'estimate_set' => 'Estimasi ditetapkan',
                                                        'estimate_revised' => 'Estimasi direvisi',
                                                        'blocked' => 'Kendala',
                                                        default => $update->type,
                                                    };
                                                @endphp
                                                <li wire:key="readonly-release-update-{{ $update->id }}"
                                                    class="text-xs text-slate-600">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <p class="font-semibold text-slate-700">
                                                            {{ $task->releaseTeamMember?->name ?? 'Tim Rilis' }} &middot; {{ $updateLabel }}
                                                        </p>
                                                        <p class="text-xs text-slate-500">
                                                            {{ $update->created_at?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') }} WIB
                                                        </p>
                                                    </div>
                                                    @if ($update->new_estimated_days !== null)
                                                        <p class="mt-1">
                                                            @if ($update->old_estimated_days !== null)
                                                                {{ $update->old_estimated_days }} hari &rarr; {{ $update->new_estimated_days }} hari
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
            @endif
