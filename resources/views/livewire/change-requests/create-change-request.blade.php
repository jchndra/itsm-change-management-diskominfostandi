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
                    <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Pengajuan</span>
                    <p class="mt-1 font-medium text-slate-800">
                        {{ $submittedAt?->copy()->locale('id')->translatedFormat('d M Y, H:i') ?? '-' }}
                    </p>
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
                <label for="application_id" class="block text-sm font-semibold text-slate-700 mb-1">Sistem / Aplikasi
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

        <!-- Deskripsi Perubahan -->
        <div>
            <label for="description" class="block text-sm font-semibold text-slate-700 mb-1">
                Apa yang diubah dan tujuannya *
            </label>
            <textarea id="description" wire:model="description" rows="4"
                placeholder="Jelaskan perubahan yang diajukan beserta tujuan yang ingin dicapai..."
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('description') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('description')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Alasan Bisnis -->
        <div>
            <label for="reason_for_change" class="block text-sm font-semibold text-slate-700 mb-1">
                Kenapa ini harus diubah? *
            </label>
            <textarea id="reason_for_change" wire:model="reason_for_change" rows="3"
                placeholder="Jelaskan latar belakang bisnis dan urgensi perubahan ini..."
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('reason_for_change') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('reason_for_change')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Usulan Tanggal dan Jam -->
        <div>
            <label for="target_implementation_date" class="block text-sm font-semibold text-slate-700 mb-1">Usulan
                Tanggal dan Jam *</label>
            <input type="datetime-local" id="target_implementation_date" wire:model="target_implementation_date"
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('target_implementation_date') ? 'border-red-500' : 'border-slate-300' }}">
            @error('target_implementation_date')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Estimasi Dampak -->
        <div>
            <label for="impact_description" class="block text-sm font-semibold text-slate-700 mb-1">Estimasi Dampak
                (opsional)</label>
            <textarea id="impact_description" wire:model="impact_description" rows="3"
                placeholder="Jelaskan estimasi downtime, jumlah pengguna terdampak, atau modul lain yang dipengaruhi..."
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('impact_description') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('impact_description')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Checkbox & Input Surat Fisik -->
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
            <label class="flex items-center text-sm font-medium text-slate-700 cursor-pointer">
                <input type="checkbox" wire:model.live="is_physical_letter"
                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mr-2.5">
                Pengajuan Menggunakan Surat / Nota Dinas Fisik
            </label>

            @if ($is_physical_letter)
                <div class="pt-2">
                    <label for="physical_letter_number" class="block text-xs font-semibold text-slate-600 mb-1">Nomor
                        Surat Dinas / Nota Dinas *</label>
                    <input type="text" id="physical_letter_number" wire:model="physical_letter_number"
                        maxlength="100" placeholder="Contoh: 500/012/DISKOMINFO/2026"
                        class="w-full text-sm px-3.5 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('physical_letter_number') ? 'border-red-500' : 'border-slate-300' }}">
                    @error('physical_letter_number')
                        <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
                    @enderror
                </div>
            @endif
        </div>

        <!-- Surat Persetujuan Bertanda Tangan -->
        <div class="space-y-3 rounded-xl border border-indigo-200 bg-indigo-50/60 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-indigo-950">Surat Persetujuan Bertanda Tangan *</h2>
                    <p class="mt-1 text-xs leading-relaxed text-indigo-800">
                        Wajib diunggah saat mengajukan RFC untuk kategori Standar, Normal, dan Emergency. Format PDF,
                        maksimal 5 MB. Boleh dikosongkan bila baru menyimpan draft.
                    </p>
                    <p class="mt-1 text-[11px] font-medium text-indigo-700">
                        {{ config('rfc.approval_letter.note') }}
                    </p>
                </div>
                @if (config('rfc.approval_letter.temporary'))
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-800">
                        Aturan Sementara
                    </span>
                @endif
            </div>

            <input type="file" id="approval_letter" wire:model="approval_letter" accept=".pdf,application/pdf"
                class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-200">

            <div wire:loading wire:target="approval_letter" class="text-xs text-slate-500">
                Mengunggah surat persetujuan...
            </div>

            @error('approval_letter')
                <span class="block text-xs text-red-600">{{ $message }}</span>
            @enderror

            @if ($approval_letter)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm">
                    <span class="min-w-0 flex-1 truncate text-slate-700">{{ $approval_letter->getClientOriginalName() }}</span>
                    <span class="text-xs text-slate-400">{{ number_format($approval_letter->getSize() / 1024, 0, ',', '.') }} KB</span>
                    <button type="button" wire:click="removePendingApprovalLetter"
                        class="text-xs font-semibold text-rose-600 hover:text-rose-700">
                        Hapus
                    </button>
                </div>
            @endif
        </div>

        <!-- Lampiran -->
        <div class="space-y-3">
            <div>
                <label for="attachments" class="block text-sm font-semibold text-slate-700 mb-1">Lampiran Pendukung</label>
                <p class="text-xs text-slate-500">
                    PDF, JPG, JPEG, atau PNG. Maksimal 5 file pendukung per RFC dan 5 MB per file. Wajib diunggah saat
                    mengajukan jika menggunakan surat/nota dinas fisik.
                </p>
            </div>

            <input type="file" id="attachments" wire:model="attachments" multiple accept=".pdf,.jpg,.jpeg,.png"
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

        <!-- Tombol Aksi Form -->
        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100">
            <button type="button" wire:click="save('{{ \App\Enums\RfcStatus::DRAFT->value }}')" wire:loading.attr="disabled"
                class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-lg text-sm transition disabled:opacity-60">
                Simpan Draft
            </button>

            <button type="button" wire:click="save('{{ \App\Enums\RfcStatus::SUBMITTED->value }}')" wire:loading.attr="disabled"
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm shadow-sm transition disabled:opacity-60">
                Ajukan Sekarang
            </button>
        </div>

    </form>
</div>
