<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('change-requests.show', $rfc->id) }}"
                    class="text-slate-400 transition hover:text-slate-600" wire:navigate>
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-slate-900">Catat Implementasi</h1>
            </div>
            <p class="mt-1 pl-9 text-sm text-slate-500">Pelaksanaan perubahan untuk {{ $rfc->displayIdentifier() }}</p>
        </div>
        <x-status-badge :status="$rfc->status" :tone="$rfc->displayStatusTone()" :label="$rfc->displayStatusLabel()" />
    </div>

    @error('status')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
            {{ $message }}</div>
    @enderror

    <form wire:submit="saveImplementation"
        class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div>
            <span class="mb-2 block text-sm font-semibold text-slate-700">Hasil Implementasi *</span>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach (\App\Enums\ImplementationResult::cases() as $result)
                    <label for="implementation_result_{{ $result->value }}"
                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm transition hover:border-indigo-300 hover:bg-indigo-50/40">
                        <input id="implementation_result_{{ $result->value }}" type="radio"
                            wire:model.live="implementation_result" value="{{ $result->value }}"
                            class="mt-0.5 h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="font-medium text-slate-700">{{ $result->label() }}</span>
                    </label>
                @endforeach
            </div>
            @error('implementation_result')
                <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
            @enderror
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <label for="executor_name" class="mb-1 block text-sm font-semibold text-slate-700">Pelaksana Perubahan *</label>
                <input id="executor_name" type="text" wire:model="executor_name"
                    placeholder="Contoh: Tim Rilis Diskominfostandi"
                    class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                @error('executor_name')
                    <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="report_source" class="mb-1 block text-sm font-semibold text-slate-700">Sumber Laporan *</label>
                <input id="report_source" type="text" wire:model="report_source"
                    placeholder="Contoh: Laporan tertulis Tim Rilis"
                    class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                @error('report_source')
                    <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div>
            <label for="summary" class="mb-1 block text-sm font-semibold text-slate-700">Ringkasan Hasil *</label>
            <textarea id="summary" wire:model="summary" rows="3"
                class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"></textarea>
            @error('summary')
                <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="test_results" class="mb-1 block text-sm font-semibold text-slate-700">Hasil Pengujian *</label>
            <textarea id="test_results" wire:model="test_results" rows="3"
                class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"></textarea>
            @error('test_results')
                <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
            @enderror
        </div>

        @if (in_array($implementation_result, [\App\Enums\ImplementationResult::FAILED->value, \App\Enums\ImplementationResult::ROLLED_BACK->value], true))
            <div class="space-y-6 rounded-xl border border-rose-200 bg-rose-50/50 p-4 sm:p-5">
                <div>
                    <label for="failure_notes" class="mb-1 block text-sm font-semibold text-slate-700">Catatan Kegagalan atau Rollback *</label>
                    <textarea id="failure_notes" wire:model="failure_notes" rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"></textarea>
                    @error('failure_notes')
                        <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="follow_up_action" class="mb-1 block text-sm font-semibold text-slate-700">Tindak Lanjut *</label>
                    <select id="follow_up_action" wire:model.live="follow_up_action"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        <option value="">Pilih tindak lanjut</option>
                        @foreach (\App\Enums\FollowUpAction::cases() as $action)
                            <option value="{{ $action->value }}">{{ $action->label() }}</option>
                        @endforeach
                    </select>
                    @error('follow_up_action')
                        <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="follow_up_notes" class="mb-1 block text-sm font-semibold text-slate-700">Keterangan Tindak Lanjut @if ($follow_up_action !== \App\Enums\FollowUpAction::NONE->value)*@endif</label>
                    <textarea id="follow_up_notes" wire:model="follow_up_notes" rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"></textarea>
                    @error('follow_up_notes')
                        <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        @endif

        <div class="space-y-3">
            <div>
                <label for="files" class="mb-1 block text-sm font-semibold text-slate-700">Bukti Pendukung (PDF - Maks. 5 file, 5 MB per file) *</label>
                <input id="files" type="file" wire:model="files" multiple accept=".pdf"
                    class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                <div wire:loading wire:target="files" class="mt-1 text-xs text-indigo-600">Mengunggah file...</div>
                @error('files')
                    <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
                @enderror
            </div>

            @if (count($files) > 0)
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Berkas yang akan
                        disimpan</p>
                    <ul class="divide-y divide-slate-200">
                        @foreach ($files as $index => $file)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-700">
                                        {{ $file->getClientOriginalName() }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ number_format($file->getSize() / 1024, 0, ',', '.') }} KB</p>
                                    @error('files.'.$index)
                                        <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button type="button" wire:click="removeFile({{ $index }})"
                                    class="rounded-md px-2.5 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                    Hapus
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <label for="confirmed" class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <input id="confirmed" type="checkbox" wire:model="confirmed"
                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm text-slate-700">Saya sudah memeriksa data dan bukti; bukti tidak dapat diubah setelah disimpan.</span>
        </label>
        @error('confirmed')
            <span class="block text-xs text-rose-500">{{ $message }}</span>
        @enderror

        <div class="flex justify-end border-t border-slate-100 pt-4">
            <button type="submit" wire:loading.attr="disabled" wire:target="saveImplementation"
                class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                <span wire:loading.remove wire:target="saveImplementation">Simpan Hasil Implementasi</span>
                <span wire:loading wire:target="saveImplementation">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
