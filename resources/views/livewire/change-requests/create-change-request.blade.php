<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Ajukan Request for Change (RFC)</h1>
            <p class="text-sm text-slate-500 mt-1">Lengkapi formulir di bawah ini untuk mengajukan permohonan perubahan
                sistem</p>
        </div>
        <a href="{{ route('requester.dashboard') }}" wire:navigate
            class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            Batal
        </a>
    </div>

    <!-- Form Container -->
    <form class="bg-white p-6 sm:p-8 rounded-xl border border-slate-200 shadow-sm space-y-6">

        <!-- Data Pemohon -->
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">Data Pemohon</h2>
                <span class="text-xs text-slate-500">Terisi otomatis dari profil</span>
            </div>

            @if (blank($requester?->position) || blank($requester?->phone_number))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Jabatan atau no. telepon belum lengkap.
                    <a href="{{ route('profile.show') }}" wire:navigate class="font-semibold underline">
                        Lengkapi profil
                    </a>
                    agar data pemohon pada formulir ini lengkap.
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</span>
                    <p class="mt-1 font-medium text-slate-800">{{ $requester?->name ?? '-' }}</p>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Jabatan</span>
                    <p class="mt-1 font-medium text-slate-800">{{ $requester?->position ?: '-' }}</p>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Unit Kerja (OPD)</span>
                    <p class="mt-1 font-medium text-slate-800">{{ $requester?->opd?->name ?? '-' }}</p>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">No. Telepon</span>
                    <p class="mt-1 font-medium text-slate-800">{{ $requester?->phone_number ?: '-' }}</p>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Email</span>
                    <p class="mt-1 font-medium text-slate-800">{{ $requester?->email ?? '-' }}</p>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">NIP</span>
                    <p class="mt-1 font-medium text-slate-800">{{ $requester?->nip ?: '-' }}</p>
                </div>
            </div>
        </div>

        <!-- Judul Perubahan -->
        <div>
            <label for="title" class="block text-sm font-semibold text-slate-700 mb-1">Judul Perubahan *</label>
            <input type="text" id="title" wire:model="title"
                placeholder="Contoh: Pembaruan Modul Integrasi Database API"
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('title') ? 'border-red-500' : 'border-slate-300' }}">
            @error('title')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Kategori Perubahan -->
            <div>
                <label for="change_type" class="block text-sm font-semibold text-slate-700 mb-1">Kategori Perubahan *</label>
                <select id="change_type" wire:model="change_type"
                    class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('change_type') ? 'border-red-500' : 'border-slate-300' }}">
                    <option value="">-- Pilih Kategori --</option>
                    <option value="standard">Standar</option>
                    <option value="normal">Normal</option>
                    <option value="emergency">Emergency</option>
                </select>
                @error('change_type')
                    <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
                @enderror
            </div>

            <!-- Aplikasi / Sistem Tujuan -->
            <div>
                <label for="application_id" class="block text-sm font-semibold text-slate-700 mb-1">Sistem / Layanan
                    yang Terdampak *</label>
                <select id="application_id" wire:model="application_id"
                    class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('application_id') ? 'border-red-500' : 'border-slate-300' }}">
                    <option value="">-- Pilih Aplikasi --</option>
                    @foreach ($applications as $app)
                        <option value="{{ $app->id }}">
                            {{ $app->name }} ({{ $app->opd->code ?? 'OPD' }})
                        </option>
                    @endforeach
                </select>
                @error('application_id')
                    <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <!-- Detail Perubahan -->
        <div>
            <label for="description" class="block text-sm font-semibold text-slate-700 mb-1">
                Detail Perubahan *
            </label>
            <textarea id="description" wire:model="description" rows="4"
                placeholder="Jelaskan perubahan yang diajukan beserta tujuan yang ingin dicapai..."
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('description') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('description')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Alasan Perubahan -->
        <div>
            <label for="reason_for_change" class="block text-sm font-semibold text-slate-700 mb-1">
                Alasan Perubahan *
            </label>
            <textarea id="reason_for_change" wire:model="reason_for_change" rows="3"
                placeholder="Jelaskan latar belakang dan urgensi perubahan ini..."
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('reason_for_change') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('reason_for_change')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Dasar Hukum -->
        <div>
            <label for="legal_basis" class="block text-sm font-semibold text-slate-700 mb-1">
                Dasar Hukum *
            </label>
            <textarea id="legal_basis" wire:model="legal_basis" rows="3"
                placeholder="Sebutkan dasar hukum atau regulasi yang melandasi perubahan ini..."
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('legal_basis') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('legal_basis')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Risiko Perubahan -->
        <div>
            <label for="change_risk" class="block text-sm font-semibold text-slate-700 mb-1">
                Risiko Perubahan *
            </label>
            <textarea id="change_risk" wire:model="change_risk" rows="3"
                placeholder="Jelaskan risiko yang mungkin timbul jika perubahan dilakukan atau tidak dilakukan..."
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('change_risk') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('change_risk')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Register Risiko -->
        <div>
            <label for="risk_event" class="block text-sm font-semibold text-slate-700 mb-1">
                Register Risiko
            </label>
            <select id="risk_event" wire:model="risk_event"
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('risk_event') ? 'border-red-500' : 'border-slate-300' }}">
                <option value="">-- Pilih Register Risiko --</option>
                @foreach (\App\Enums\RiskEvent::cases() as $riskEvent)
                    <option value="{{ $riskEvent->value }}">{{ $riskEvent->optionLabel() }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">
                Pilih kejadian risiko pada gambar Panduan Manajemen Risiko
                <a href="{{ asset('images/register-risiko-indikatif.png') }}" target="_blank" rel="noopener"
                    class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
                    Lampiran B — Register Risiko Indikatif Tingkat Kota (Tabel B.1)
                </a>.
            </p>
            @error('risk_event')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Analisis Dampak dan Risiko Perubahan -->
        <section class="rounded-xl border-2 border-slate-300 p-5 sm:p-6 space-y-4"
            x-data="{
                dampak: @js(old('level_dampak', '')),
                kemungkinan: @js(old('level_kemungkinan', '')),
                besaran: @js(old('besaran_risiko', '')),
                level: @js(old('level_risiko', '')),
                prioritas: {
                    1: [0, 25, 23, 21, 18, 16],
                    2: [0, 24, 19, 14, 12, 9],
                    3: [0, 22, 15, 11, 7, 5],
                    4: [0, 20, 13, 8, 4, 2],
                    5: [0, 17, 10, 6, 3, 1],
                },
                hitung() {
                    if (!this.dampak || !this.kemungkinan) {
                        this.besaran = '';
                        this.level = '';
                        return;
                    }

                    const nilaiPrioritas = this.prioritas[this.kemungkinan]?.[Number(this.dampak)];

                    if (!nilaiPrioritas) {
                        this.besaran = '';
                        this.level = '';
                        return;
                    }

                    this.besaran = String(26 - nilaiPrioritas);
                    this.level = nilaiPrioritas <= 3 ? '5'
                        : nilaiPrioritas <= 8 ? '4'
                        : nilaiPrioritas <= 17 ? '3'
                        : nilaiPrioritas <= 22 ? '2'
                        : '1';
                },
                init() {
                    this.hitung();
                },
            }">
            <h2 class="text-center text-base font-bold text-slate-800">Analisis Dampak dan Risiko Perubahan</h2>

            <div class="text-center">
                <a href="{{ route('rfc-documents.panduan-analisis-risiko') }}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    Panduan Pengisian Analisis Risiko
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Level Dampak -->
                <div>
                    <label for="level_dampak" class="block text-sm font-semibold text-slate-700 mb-1">Level Dampak</label>
                    <select id="level_dampak" name="level_dampak" x-model="dampak" x-on:change="hitung()"
                        class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- Pilih Level Dampak --</option>
                        <option value="1">Tidak Signifikan (1)</option>
                        <option value="2">Minor (2)</option>
                        <option value="3">Moderat (3)</option>
                        <option value="4">Signifikan (4)</option>
                        <option value="5">Sangat Signifikan (5)</option>
                    </select>
                </div>

                <!-- Level Kemungkinan -->
                <div>
                    <label for="level_kemungkinan" class="block text-sm font-semibold text-slate-700 mb-1">Level Kemungkinan</label>
                    <select id="level_kemungkinan" name="level_kemungkinan" x-model="kemungkinan" x-on:change="hitung()"
                        class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- Pilih Level Kemungkinan --</option>
                        <option value="1">Hampir Tidak Terjadi (1)</option>
                        <option value="2">Jarang Terjadi (2)</option>
                        <option value="3">Kadang Terjadi (3)</option>
                        <option value="4">Sering Terjadi (4)</option>
                        <option value="5">Hampir Pasti Terjadi (5)</option>
                    </select>
                </div>

                <!-- Besaran Risiko -->
                <div>
                    <label for="besaran_risiko" class="block text-sm font-semibold text-slate-700 mb-1">Besaran Risiko</label>
                    <select id="besaran_risiko" name="besaran_risiko" x-model="besaran" tabindex="-1"
                        aria-readonly="true"
                        class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-lg bg-slate-100 text-slate-500 cursor-not-allowed pointer-events-none">
                        <option value="">-- Besaran Risiko --</option>
                        @for ($nilai = 1; $nilai <= 25; $nilai++)
                            <option value="{{ $nilai }}">{{ $nilai }}</option>
                        @endfor
                    </select>
                </div>

                <!-- Level Risiko -->
                <div>
                    <label for="level_risiko" class="block text-sm font-semibold text-slate-700 mb-1">Level Risiko</label>
                    <select id="level_risiko" name="level_risiko" x-model="level" tabindex="-1" aria-readonly="true"
                        class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-lg bg-slate-100 text-slate-500 cursor-not-allowed pointer-events-none">
                        <option value="">-- Level Risiko --</option>
                        <option value="1">Sangat Rendah (1)</option>
                        <option value="2">Rendah (2)</option>
                        <option value="3">Sedang (3)</option>
                        <option value="4">Tinggi (4)</option>
                        <option value="5">Sangat Tinggi (5)</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Keterangan -->
        <div>
            <label for="impact_description" class="block text-sm font-semibold text-slate-700 mb-1">Keterangan
                (opsional)</label>
            <textarea id="impact_description" wire:model="impact_description" rows="3"
                placeholder="Tuliskan keterangan tambahan terkait perubahan, misalnya perkiraan downtime, jumlah pengguna terdampak, atau modul lain yang dipengaruhi..."
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('impact_description') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('impact_description')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Lampiran -->
        <div class="space-y-3">
            <div>
                <label for="attachments" class="block text-sm font-semibold text-slate-700 mb-1">Lampiran *</label>
                <p class="text-xs text-slate-500">
                    Berkas pendukung lainnya. Format PDF Maksimal 5 file per RFC dan 5 MB per file.
                </p>
            </div>

            <!-- Lampiran Pendukung -->
            <input type="file" id="attachments" wire:model="attachments" multiple accept=".pdf"
                class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">

            <div wire:loading wire:target="attachments" class="text-xs text-slate-500">
                Mengunggah lampiran...
            </div>

            @error('attachments')
                <span class="text-xs text-red-500 block">{{ $message }}</span>
            @enderror

            @if (count($attachments) > 0)
                <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                    @foreach ($attachments as $index => $file)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
                            wire:key="pending-attachment-{{ $index }}-{{ $file->getFilename() }}">
                            <span class="min-w-0 flex-1 truncate text-slate-700">{{ $file->getClientOriginalName() }}</span>
                            <span class="text-xs text-slate-400">{{ number_format($file->getSize() / 1024, 0, ',', '.') }} KB</span>
                            <button type="button" wire:click="removePendingAttachment({{ $index }})"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-700">
                                Hapus
                            </button>
                            @error('attachments.'.$index)
                                <span class="w-full text-xs text-red-500">{{ $message }}</span>
                            @enderror
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <!-- Pernyataan Persetujuan Pimpinan (Eselon II) -->
        <div>
            <label class="flex items-start text-sm font-medium text-slate-700 cursor-pointer">
                <input type="checkbox" wire:model.live="head_of_department_approved"
                    class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mr-2.5">
                <span>Saya menyatakan bahwa perubahan ini telah disetujui oleh Pimpinan (Eselon II).</span>
            </label>
            @error('head_of_department_approved')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Tombol Aksi Form -->
        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100">
            <button type="button" wire:click="save('{{ \App\Enums\RfcStatus::DRAFT->value }}')" wire:loading.attr="disabled"
                class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-lg text-sm transition disabled:opacity-60">
                Simpan Draft
            </button>

            <button type="button" wire:click="save('{{ \App\Enums\RfcStatus::TICKET_CREATED->value }}')" wire:loading.attr="disabled"
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm shadow-sm transition disabled:opacity-60">
                Ajukan Sekarang
            </button>
        </div>

    </form>
</div>
