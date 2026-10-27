<div class="max-w-4xl mx-auto space-y-6">
    @php
        $submittedCategory = match ((string) $rfc->change_type) {
            'standard' => 'Standar',
            'normal' => 'Normal',
            'emergency' => 'Emergency',
            default => '-',
        };
    @endphp
    {{-- Top Bar Navigation --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('change-requests.index') }}"
            class="inline-flex items-center text-xs font-semibold text-slate-600 hover:text-blue-900 transition">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali ke Dashboard
        </a>
        <span class="text-xs text-slate-500 font-medium">Evaluasi & Penilaian Risiko (Change Manager)</span>
    </div>

    @include('livewire.change-requests.partials.rfc-summary', ['rfc' => $rfc])

    {{-- Form Evaluasi Risiko & Klasifikasi --}}
    <form wire:submit="submitAssessment" class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
        <div>
            <h3 class="text-sm font-bold text-slate-800">Penilaian Risiko & Klasifikasi Perubahan</h3>
            <p class="text-xs text-slate-500">Analisis tingkat risiko, dampak, urgensi, dan tentukan klasifikasi
                perubahan sesuai kriteria ITIL</p>
        </div>

        @error('status')
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
                {{ $message }}</div>
        @enderror

        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-medium text-slate-700">
            Kategori yang diusulkan Pemohon: {{ $submittedCategory }}
        </div>

        @if ($rfc->emergency_unconfirmed)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800">
                Pemohon mengusulkan kategori Emergency. Konfirmasi dengan mempertahankan klasifikasi Emergency Change, atau pilih klasifikasi lain bila tidak memenuhi syarat darurat.
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
            {{-- Tingkat Risiko --}}
            <div class="space-y-2">
                <label class="block font-bold text-slate-700">Tingkat Risiko <span
                        class="text-rose-500">*</span></label>
                <select wire:model.live="risk_level"
                    class="w-full p-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="Rendah">Rendah (Dampak minimal pada sistem)</option>
                    <option value="Sedang">Sedang (Dampak terbatas pada fungsi tertentu)</option>
                    <option value="Tinggi">Tinggi (Berpotensi mengganggu layanan utama)</option>
                    <option value="Kritis">Kritis (Risiko tinggi penghentian layanan publik)</option>
                </select>
                @error('risk_level')
                    <span class="text-rose-600 font-medium">{{ $message }}</span>
                @enderror
            </div>

            {{-- Tingkat Dampak --}}
            <div class="space-y-2">
                <label class="block font-bold text-slate-700">Tingkat Dampak <span
                        class="text-rose-500">*</span></label>
                <select wire:model.live="impact_level"
                    class="w-full p-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="Rendah">Rendah (Internal OPD)</option>
                    <option value="Sedang">Sedang (Beberapa Unit Kerja / OPD)</option>
                    <option value="Tinggi">Tinggi (Seluruh Pemerintah Kota Bekasi)</option>
                    <option value="Kritis">Kritis (Layanan Publik Masyarakat)</option>
                </select>
                @error('impact_level')
                    <span class="text-rose-600 font-medium">{{ $message }}</span>
                @enderror
            </div>

            {{-- Urgensi --}}
            <div class="space-y-2">
                <label class="block font-bold text-slate-700">Urgensi Perubahan <span
                        class="text-rose-500">*</span></label>
                <select wire:model.live="urgency_level"
                    class="w-full p-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="Rendah">Rendah (Dapat dijadwalkan rutin)</option>
                    <option value="Normal">Normal (Siklus rilis standar)</option>
                    <option value="Tinggi">Tinggi (Membutuhkan penanganan segera)</option>
                    <option value="Emergency">Emergency (Penanganan darurat langsung)</option>
                </select>
                @error('urgency_level')
                    <span class="text-rose-600 font-medium">{{ $message }}</span>
                @enderror
            </div>

            {{-- Klasifikasi Perubahan --}}
            <div class="space-y-2">
                <label class="block font-bold text-slate-700">Klasifikasi Perubahan <span
                        class="text-rose-500">*</span></label>
                <select wire:model.live="classification"
                    class="w-full p-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="Standard Change">Standard Change (Rutin & Ter-pre-otorisasi)</option>
                    <option value="Normal Change">Normal Change (Melalui review dan persetujuan Change Manager)</option>
                    <option value="Emergency Change">Emergency Change (Penanganan segera dan diprioritaskan)</option>
                </select>
                @error('classification')
                    <span class="text-rose-600 font-medium">{{ $message }}</span>
                @enderror
            </div>
        </div>

        {{-- Catatan Analisis --}}
        <div class="space-y-2 text-xs">
            <label class="block font-bold text-slate-700">Catatan Analisis Risiko & Rencana Mitigasi <span
                    class="text-rose-500">*</span></label>
            <textarea wire:model="analysis_notes" rows="4"
                placeholder="Tuliskan catatan analisis potensi kegagalan, prosedur pengujian, serta rencana pengembalian (rollback plan)..."
                class="w-full p-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
            @error('analysis_notes')
                <span class="text-rose-600 font-medium">{{ $message }}</span>
            @enderror
        </div>

        {{-- Panel Preview Indikator Visual --}}
        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-2 text-xs">
            <p class="font-bold text-slate-700">Ringkasan Indikator Evaluasi:</p>
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-3 py-1 bg-amber-100 text-amber-800 rounded-lg font-semibold">Risiko:
                    {{ $risk_level }}</span>
                <span class="px-3 py-1 bg-amber-100 text-amber-800 rounded-lg font-semibold">Dampak:
                    {{ $impact_level }}</span>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-lg font-semibold">Urgensi:
                    {{ $urgency_level }}</span>
                <span class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-lg font-semibold">Klasifikasi:
                    {{ $classification }}</span>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="{{ route('change-requests.index') }}"
                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                Batal
            </a>
            <button type="submit"
                class="px-5 py-2 bg-blue-900 hover:bg-blue-800 text-white text-xs font-semibold rounded-xl shadow transition">
                Simpan Penilaian & Selesaikan Evaluasi
            </button>
        </div>
    </form>
</div>
