<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header Navigation & Title -->
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('change-requests.show', $rfc->id) }}"
                    class="text-slate-400 hover:text-slate-600 transition" wire:navigate>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-slate-900">Pencatatan Keputusan Change Manager</h1>
            </div>
            <p class="text-sm text-slate-500 mt-1 pl-9">Keputusan resmi Change Manager untuk
                {{ $rfc->displayIdentifier() }}</p>
        </div>
        <a href="{{ route('change-requests.show', $rfc->id) }}"
            class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
            wire:navigate>
            Batal
        </a>
    </div>

    <!-- Ringkasan Informasi RFC -->
    <div class="bg-slate-50 p-4 border border-slate-200 rounded-xl space-y-2 text-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div><span class="text-slate-500 block text-xs font-semibold uppercase tracking-wider">Judul
                    Perubahan:</span> <strong class="text-slate-800">{{ $rfc->title }}</strong></div>
            <div><span class="text-slate-500 block text-xs font-semibold uppercase tracking-wider">Aplikasi
                    Target:</span> <strong class="text-slate-800">{{ $rfc->application->name ?? '-' }}</strong></div>
            <div><span class="text-slate-500 block text-xs font-semibold uppercase tracking-wider">Pemohon (Submitted
                    By):</span> <strong class="text-slate-800">{{ $rfc->submittedBy->name ?? '-' }}</strong></div>
            <div><span class="text-slate-500 block text-xs font-semibold uppercase tracking-wider">OPD Pengaju:</span>
                <strong class="text-slate-800">{{ $rfc->application->opd->name ?? '-' }}</strong>
            </div>
        </div>
    </div>

    {{-- Informasi Tiket dari Admin (baca-saja) --}}
    @if (filled($rfc->ticket_number) || filled($rfc->priority) || filled($rfc->category) || filled($rfc->admin_note))
        <div class="bg-white p-5 border border-slate-200 rounded-xl shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-slate-800">Informasi Tiket dari Admin</h2>
            <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
                <div>
                    <span class="block text-slate-500 text-xs font-semibold uppercase tracking-wider">Nomor Tiket</span>
                    <strong class="text-slate-800">{{ $rfc->ticket_number ?: '-' }}</strong>
                </div>
                <div>
                    <span class="block text-slate-500 text-xs font-semibold uppercase tracking-wider">Prioritas</span>
                    <strong class="text-slate-800">
                        {{ $rfc->priority instanceof \App\Enums\TicketPriority ? $rfc->priority->label() : (\App\Enums\TicketPriority::tryFrom((string) $rfc->priority)?->label() ?? '-') }}
                    </strong>
                </div>
                <div>
                    <span class="block text-slate-500 text-xs font-semibold uppercase tracking-wider">Kategori Layanan</span>
                    <strong class="text-slate-800">
                        {{ $rfc->category instanceof \App\Enums\ServiceCategory ? $rfc->category->label() : (\App\Enums\ServiceCategory::tryFrom((string) $rfc->category)?->label() ?? '-') }}
                    </strong>
                </div>
            </div>
            @if (filled($rfc->admin_note))
                <div class="border-t border-slate-100 pt-3">
                    <span class="block text-slate-500 text-xs font-semibold uppercase tracking-wider">Catatan Admin untuk Change Manager</span>
                    <p class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ $rfc->admin_note }}</p>
                </div>
            @endif
        </div>
    @endif

    @error('status')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
            {{ $message }}</div>
    @enderror

    @unless ($rfc->riskAssessment)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-700">
            Analisis risiko belum dilakukan. Keputusan Change Manager belum dapat direkam.
        </div>
    @endunless

    <!-- Form Pencatatan Keputusan -->
    <form wire:submit.prevent="saveDecision"
        class="bg-white p-6 sm:p-8 rounded-xl border border-slate-200 shadow-sm space-y-6">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Pencatat Keputusan -->
            <div>
                <span class="block text-sm font-semibold text-slate-700 mb-1">Pencatat Keputusan</span>
                <p class="w-full text-sm px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-slate-700">
                    Dicatat atas nama: {{ $recordedBy?->name }}, {{ filled($recordedBy?->position) ? $recordedBy->position : 'Change Manager' }}
                </p>
            </div>

            <!-- Keputusan Change Manager -->
            <div>
                <label for="decisionStatus" class="block text-sm font-semibold text-slate-700 mb-1">Keputusan Change Manager
                    *</label>
                <select id="decisionStatus" wire:model="decisionStatus"
                    class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('decisionStatus') ? 'border-red-500' : 'border-slate-300' }}">
                    @foreach (\App\Enums\DecisionStatus::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
                @error('decisionStatus')
                    <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Jenis Dokumen Pendukung -->
            <div>
                <label for="documentType" class="block text-sm font-semibold text-slate-700 mb-1">Jenis Dokumen
                    Pendukung *</label>
                <select id="documentType" wire:model="documentType"
                    class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('documentType') ? 'border-red-500' : 'border-slate-300' }}">
                    <option value="cab_minutes">Notulen / Risalah Rapat</option>
                    <option value="ecab_approval">Persetujuan Darurat</option>
                    <option value="delegation_letter">Surat Mandat / Delegasi Keputusan</option>
                    <option value="emergency_confirmation">Konfirmasi Darurat (Emergency Confirmation)</option>
                </select>
                @error('documentType')
                    <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
                @enderror
            </div>

            <!-- Nomor Dokumen/Surat -->
            <div>
                <label for="documentNumber" class="block text-sm font-semibold text-slate-700 mb-1">Nomor Dokumen /
                    Surat Pendukung *</label>
                <input type="text" id="documentNumber" wire:model="documentNumber"
                    placeholder="Contoh: 800/045/BA/2026"
                    class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('documentNumber') ? 'border-red-500' : 'border-slate-300' }}">
                @error('documentNumber')
                    <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <!-- Unggah File PDF -->
        <div>
            <label for="documentFile" class="block text-sm font-semibold text-slate-700 mb-1">Unggah Dokumen Pendukung
                (PDF, Maksimal 10MB) *</label>
            <input type="file" id="documentFile" wire:model="documentFile" accept="application/pdf"
                class="w-full text-sm px-3.5 py-2 border rounded-lg border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">

            <div wire:loading wire:target="documentFile" class="text-xs text-indigo-600 mt-1">Mengunggah dan mengartikan
                checksum berkas...</div>
            @error('documentFile')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Catatan Keputusan -->
        <div>
            <label for="notes" class="block text-sm font-semibold text-slate-700 mb-1">Catatan Keputusan &
                Pertimbangan *</label>
            <textarea id="notes" wire:model="notes" rows="3"
                placeholder="Masukkan ringkasan pertimbangan, catatan mitigasi risiko, atau instruksi teknis..."
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('notes') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('notes')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end pt-4 border-t border-slate-100">
            <button type="submit" wire:loading.attr="disabled"
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-medium rounded-lg text-sm shadow-sm transition flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Simpan Keputusan</span>
            </button>
        </div>
    </form>
</div>
