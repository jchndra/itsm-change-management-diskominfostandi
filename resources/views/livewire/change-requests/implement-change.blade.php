@php
    $isFailureResult = in_array($implementation_result, [
        \App\Enums\ImplementationResult::FAILED->value,
        \App\Enums\ImplementationResult::ROLLED_BACK->value,
    ], true);

    $isSuccessResult = $implementation_result === \App\Enums\ImplementationResult::SUCCESS->value;

    $task = $releaseTask ?? null;

    $taskStatusBadge = match ($task?->execution_status) {
        'pending' => ['label' => 'Menunggu dimulai', 'classes' => 'bg-slate-100 text-slate-700 ring-slate-200'],
        'in_progress' => ['label' => 'Sedang dikerjakan', 'classes' => 'bg-blue-100 text-blue-700 ring-blue-200'],
        'successful' => ['label' => 'Selesai', 'classes' => 'bg-emerald-100 text-emerald-700 ring-emerald-200'],
        'rolled_back' => ['label' => 'Rollback', 'classes' => 'bg-rose-100 text-rose-700 ring-rose-200'],
        default => ['label' => 'Belum tersedia', 'classes' => 'bg-slate-100 text-slate-700 ring-slate-200'],
    };

    $steps = [
        ['label' => 'Estimasi', 'state' => 'done'],
        ['label' => 'Dikerjakan', 'state' => 'done'],
        ['label' => 'Selesai / Rollback', 'state' => 'active'],
    ];

    $fieldClasses = 'block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500';
    $cardClasses = 'rounded-xl border border-slate-200 bg-white shadow-sm';
@endphp

<div class="mx-auto max-w-4xl space-y-6">
    {{-- Kepala halaman --}}
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-4">
        <div class="flex min-w-0 items-start gap-3">
            <a href="{{ route('change-requests.show', $rfc->id) }}" wire:navigate
                class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
                aria-label="Kembali ke detail RFC">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
            </a>
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-slate-900">Catat Implementasi</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Pelaksanaan perubahan untuk <span class="font-semibold text-slate-700">{{ $rfc->displayIdentifier() }}</span>
                </p>
            </div>
        </div>
        <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
    </div>

    @error('status')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700" role="alert">
            {{ $message }}</div>
    @enderror

    {{-- Ringkasan tugas (baca-saja) --}}
    <section class="rounded-xl border border-emerald-100 bg-white p-4 shadow-sm sm:p-6"
        aria-labelledby="ringkasan-tugas-heading">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <h2 id="ringkasan-tugas-heading" class="text-lg font-bold text-slate-800">Ringkasan Tugas</h2>
                    <p class="text-xs text-slate-500">Tugas Tim Rilis yang sedang Anda kerjakan.</p>
                </div>
            </div>
            <span
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $taskStatusBadge['classes'] }}">
                <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                {{ $taskStatusBadge['label'] }}
            </span>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status</p>
                <p class="mt-1 font-semibold text-slate-800">{{ $taskStatusBadge['label'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Estimasi</p>
                <p class="mt-1 font-semibold text-slate-800">
                    {{ $task?->estimated_days ? $task->estimated_days.' hari' : 'Belum ditetapkan' }}
                </p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Mulai (WIB)</p>
                <p class="mt-1 font-semibold text-slate-800">
                    {{ $task?->scheduled_start
                        ? $task->scheduled_start->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i')
                        : '—' }}
                </p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Target selesai (WIB)</p>
                <p class="mt-1 font-semibold text-slate-800">
                    {{ $task?->scheduled_end
                        ? $task->scheduled_end->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i')
                        : '—' }}
                </p>
            </div>
        </div>

        <p class="mt-3 flex items-start gap-2 text-xs text-slate-500">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            <span>Waktu selesai dicatat otomatis saat disimpan.</span>
        </p>

        <ol class="mt-5 grid grid-cols-3 gap-1" aria-label="Tahapan pencatatan implementasi">
            @foreach ($steps as $step)
                @php
                    $stepStateClasses = match ($step['state']) {
                        'done' => 'bg-emerald-600 text-white',
                        'active' => 'bg-emerald-600 text-white ring-4 ring-emerald-100',
                        default => 'bg-slate-200 text-slate-500',
                    };
                    $stepLabelClasses = match ($step['state']) {
                        'done' => 'text-emerald-700',
                        'active' => 'text-emerald-800',
                        default => 'text-slate-500',
                    };
                @endphp
                <li class="relative flex flex-col items-center text-center"
                    @if ($step['state'] === 'active') aria-current="step" @endif>
                    @unless ($loop->last)
                        <span class="absolute left-1/2 top-4 h-0.5 w-full {{ $step['state'] === 'done' ? 'bg-emerald-300' : 'bg-slate-200' }}"
                            aria-hidden="true"></span>
                    @endunless
                    <span
                        class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full {{ $stepStateClasses }}">
                        @if ($step['state'] === 'done')
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                                stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        @else
                            <span class="text-xs font-bold">{{ $loop->iteration }}</span>
                        @endif
                    </span>
                    <span
                        class="relative z-10 mt-2 bg-white px-1 text-[11px] font-semibold leading-tight sm:text-xs {{ $stepLabelClasses }}">
                        {{ $step['label'] }}
                    </span>
                </li>
            @endforeach
        </ol>
    </section>

    <form wire:submit="saveImplementation" class="space-y-6">
        {{-- 1. Hasil Implementasi --}}
        <section class="{{ $cardClasses }} p-5 sm:p-6" aria-labelledby="hasil-implementasi-heading">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200">1</span>
                <div class="min-w-0">
                    <h2 id="hasil-implementasi-heading" class="text-base font-bold text-slate-800">
                        Hasil Implementasi <span class="text-rose-500" aria-hidden="true">*</span>
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">Pilih hasil akhir pelaksanaan perubahan.</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach (\App\Enums\ImplementationResult::cases() as $result)
                    @php
                        $resultAccent = match ($result) {
                            \App\Enums\ImplementationResult::SUCCESS => [
                                'card' => 'peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:ring-emerald-500',
                                'icon' => 'bg-emerald-100 text-emerald-700',
                            ],
                            \App\Enums\ImplementationResult::FAILED => [
                                'card' => 'peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:ring-amber-500',
                                'icon' => 'bg-amber-100 text-amber-700',
                            ],
                            \App\Enums\ImplementationResult::ROLLED_BACK => [
                                'card' => 'peer-checked:border-rose-500 peer-checked:bg-rose-50 peer-checked:ring-rose-500',
                                'icon' => 'bg-rose-100 text-rose-700',
                            ],
                        };
                    @endphp
                    <label for="implementation_result_{{ $result->value }}" class="block cursor-pointer">
                        <input id="implementation_result_{{ $result->value }}" type="radio"
                            wire:model.live="implementation_result" value="{{ $result->value }}"
                            class="peer sr-only">
                        <span
                            class="flex h-full items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 peer-checked:ring-2 peer-focus-visible:ring-2 peer-focus-visible:ring-slate-500 peer-focus-visible:ring-offset-2 {{ $resultAccent['card'] }}">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $resultAccent['icon'] }}">
                                @switch($result)
                                    @case(\App\Enums\ImplementationResult::SUCCESS)
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    @break

                                    @case(\App\Enums\ImplementationResult::FAILED)
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 9v3.75m0 3h.008v.008H12v-.008ZM10.34 3.94l-7.5 13a1.5 1.5 0 0 0 1.3 2.25h15a1.5 1.5 0 0 0 1.3-2.25l-7.5-13a1.5 1.5 0 0 0-2.6 0Z" />
                                        </svg>
                                    @break

                                    @default
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                                        </svg>
                                @endswitch
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-800">{{ $result->label() }}</span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-slate-500">
                                    @switch($result)
                                        @case(\App\Enums\ImplementationResult::SUCCESS)
                                            Perubahan berjalan sesuai rencana.
                                        @break

                                        @case(\App\Enums\ImplementationResult::FAILED)
                                            Perubahan gagal diterapkan.
                                        @break

                                        @default
                                            Perubahan dikembalikan ke kondisi semula.
                                    @endswitch
                                </span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('implementation_result')
                <p class="mt-2 text-xs font-medium text-rose-600" role="alert">{{ $message }}</p>
            @enderror
        </section>

        {{-- 2. Laporan Pelaksanaan --}}
        <section class="{{ $cardClasses }} p-5 sm:p-6" aria-labelledby="laporan-pelaksanaan-heading">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200">2</span>
                <div class="min-w-0">
                    <h2 id="laporan-pelaksanaan-heading" class="text-base font-bold text-slate-800">Laporan Pelaksanaan</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Pelaksana, sumber laporan, dan hasil pengujian.</p>
                </div>
            </div>

            <div class="mt-4 space-y-5">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="min-w-0">
                        <label for="executor_name" class="mb-1 block text-sm font-semibold text-slate-700">
                            Pelaksana Perubahan <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <input id="executor_name" type="text" wire:model="executor_name"
                            placeholder="Contoh: Tim Rilis Diskominfostandi" class="{{ $fieldClasses }}">
                        @error('executor_name')
                            <p class="mt-1 text-xs font-medium text-rose-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="min-w-0">
                        <label for="report_source" class="mb-1 block text-sm font-semibold text-slate-700">
                            Sumber Laporan <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <input id="report_source" type="text" wire:model="report_source"
                            placeholder="Contoh: Laporan tertulis Tim Rilis" class="{{ $fieldClasses }}">
                        @error('report_source')
                            <p class="mt-1 text-xs font-medium text-rose-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="summary" class="mb-1 block text-sm font-semibold text-slate-700">
                        Ringkasan Hasil <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <textarea id="summary" wire:model="summary" rows="3" class="{{ $fieldClasses }} resize-y"></textarea>
                    @error('summary')
                        <p class="mt-1 text-xs font-medium text-rose-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="test_results" class="mb-1 block text-sm font-semibold text-slate-700">
                        Hasil Pengujian <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <textarea id="test_results" wire:model="test_results" rows="3" class="{{ $fieldClasses }} resize-y"></textarea>
                    @error('test_results')
                        <p class="mt-1 text-xs font-medium text-rose-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- 3. Kegagalan / Rollback (kondisional) --}}
        @if ($isFailureResult)
            <section class="rounded-xl border border-rose-200 bg-rose-50/60 p-5 shadow-sm sm:p-6"
                aria-labelledby="kegagalan-heading">
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-100 text-sm font-bold text-rose-700 ring-1 ring-inset ring-rose-200">3</span>
                    <div class="min-w-0">
                        <h2 id="kegagalan-heading" class="text-base font-bold text-rose-900">Kegagalan / Rollback</h2>
                        <p class="mt-0.5 text-xs text-rose-700">Wajib dilengkapi karena hasil bukan Berhasil.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-5">
                    <div>
                        <label for="failure_notes" class="mb-1 block text-sm font-semibold text-rose-900">
                            Catatan Kegagalan atau Rollback <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <textarea id="failure_notes" wire:model="failure_notes" rows="3"
                            class="{{ $fieldClasses }} resize-y border-rose-300 focus:border-rose-500 focus:ring-rose-500"></textarea>
                        @error('failure_notes')
                            <p class="mt-1 text-xs font-medium text-rose-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="follow_up_action" class="mb-1 block text-sm font-semibold text-rose-900">
                            Tindak Lanjut <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <select id="follow_up_action" wire:model.live="follow_up_action"
                            class="{{ $fieldClasses }} border-rose-300 focus:border-rose-500 focus:ring-rose-500">
                            <option value="">Pilih tindak lanjut</option>
                            @foreach (\App\Enums\FollowUpAction::cases() as $action)
                                <option value="{{ $action->value }}">{{ $action->label() }}</option>
                            @endforeach
                        </select>
                        @error('follow_up_action')
                            <p class="mt-1 text-xs font-medium text-rose-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="follow_up_notes" class="mb-1 block text-sm font-semibold text-rose-900">
                            Keterangan Tindak Lanjut @if ($follow_up_action !== \App\Enums\FollowUpAction::NONE->value)<span class="text-rose-500" aria-hidden="true">*</span>@endif
                        </label>
                        <textarea id="follow_up_notes" wire:model="follow_up_notes" rows="3"
                            class="{{ $fieldClasses }} resize-y border-rose-300 focus:border-rose-500 focus:ring-rose-500"></textarea>
                        @error('follow_up_notes')
                            <p class="mt-1 text-xs font-medium text-rose-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>
        @endif

        {{-- 4. Bukti Pendukung --}}
        <section class="{{ $cardClasses }} p-5 sm:p-6" aria-labelledby="bukti-pendukung-heading">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200">4</span>
                <div class="min-w-0">
                    <h2 id="bukti-pendukung-heading" class="text-base font-bold text-slate-800">
                        Bukti Pendukung <span class="text-rose-500" aria-hidden="true">*</span>
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">Unggah bukti pelaksanaan dalam format PDF.</p>
                </div>
            </div>

            <div class="mt-4 space-y-4">
                <label for="files"
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50/40 px-4 py-8 text-center transition hover:border-emerald-400 hover:bg-emerald-50 focus-within:ring-2 focus-within:ring-emerald-600 focus-within:ring-offset-2">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white text-emerald-600 shadow-sm">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                        </svg>
                    </span>
                    <span class="text-sm font-semibold text-slate-800">Klik untuk memilih berkas PDF</span>
                    <span id="files-hint" class="text-xs text-slate-500">PDF - Maks. 5 file, 5 MB per file</span>
                    <input id="files" type="file" wire:model="files" multiple accept=".pdf"
                        aria-describedby="files-hint" class="sr-only">
                </label>

                <div wire:loading wire:target="files"
                    class="flex items-center gap-2 text-xs font-medium text-emerald-700">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                    </svg>
                    Mengunggah file...
                </div>

                @error('files')
                    <p class="text-xs font-medium text-rose-600" role="alert">{{ $message }}</p>
                @enderror

                @if (count($files) > 0)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Berkas yang akan disimpan ({{ count($files) }}/5)
                        </p>
                        <ul class="divide-y divide-slate-200">
                            @foreach ($files as $index => $file)
                                <li wire:key="implementation-file-{{ $index }}"
                                    class="flex flex-wrap items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <span
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-600">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                                                stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-800"
                                                title="{{ $file->getClientOriginalName() }}">
                                                {{ $file->getClientOriginalName() }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                {{ number_format($file->getSize() / 1024, 0, ',', '.') }} KB
                                            </p>
                                            @error('files.'.$index)
                                                <p class="mt-1 text-xs font-medium text-rose-600" role="alert">
                                                    {{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    <button type="button" wire:click="removeFile({{ $index }})"
                                        class="rounded-md px-2.5 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:ring-offset-2">
                                        Hapus
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>

        {{-- 5. Persetujuan dan simpan --}}
        <section class="{{ $cardClasses }} p-5 sm:p-6" aria-labelledby="persetujuan-heading">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200">5</span>
                <div class="min-w-0">
                    <h2 id="persetujuan-heading" class="text-base font-bold text-slate-800">Persetujuan</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Konfirmasi sebelum laporan dikirim.</p>
                </div>
            </div>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="confirmed"
                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 transition focus-within:ring-2 focus-within:ring-emerald-600 focus-within:ring-offset-2">
                        <input id="confirmed" type="checkbox" wire:model="confirmed"
                            class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm text-slate-700">
                            @if ($isSuccessResult)
                                Saya, mewakili Tim Rilis, menyatakan pekerjaan ini telah selesai dilaksanakan dan menyetujui hasil pelaksanaan beserta bukti yang dilampirkan.
                            @else
                                Saya, mewakili Tim Rilis, menyatakan pelaksanaan telah dihentikan sesuai hasil yang dipilih dan menyetujui laporan beserta bukti yang dilampirkan.
                            @endif
                            <span class="mt-1 block text-xs text-slate-500">
                                Setelah disimpan, laporan dan bukti tidak dapat diubah, dan RFC masuk review Change Manager.
                            </span>
                        </span>
                    </label>
                    @error('confirmed')
                        <p class="mt-1 text-xs font-medium text-rose-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div
                    class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">Pastikan seluruh laporan dan bukti sudah benar sebelum disimpan.</p>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveImplementation"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-emerald-400 sm:w-auto">
                        <span wire:loading.remove wire:target="saveImplementation">
                            {{ $isSuccessResult ? 'Simpan dan Selesaikan' : 'Simpan Laporan' }}
                        </span>
                        <span wire:loading wire:target="saveImplementation">Menyimpan...</span>
                    </button>
                </div>
            </div>
        </section>
    </form>
</div>
