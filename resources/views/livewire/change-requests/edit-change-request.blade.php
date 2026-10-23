<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ $isRevision ? 'Revisi RFC' : 'Edit RFC' }}
                <span class="text-slate-400">#{{ $rfc->rfc_number }}</span>
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $isRevision
                    ? 'Perbaiki data RFC sesuai catatan Admin, lalu kirim revisi.'
                    : 'Perbarui data RFC sebelum mengajukannya.' }}
            </p>
        </div>
        <a href="{{ route('change-requests.show', $rfc) }}" wire:navigate
            class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            Batal
        </a>
    </div>

    @if ($isRevision && filled($revisionReason))
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="text-sm font-bold text-amber-900">Alasan Permintaan Revisi dari Admin</h2>
            <p class="mt-2 whitespace-pre-wrap text-sm text-amber-800">{{ $revisionReason }}</p>
        </div>
    @endif

    @error('status')
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    <!-- Form Container -->
    <form class="bg-white p-6 sm:p-8 rounded-xl border border-slate-200 shadow-sm space-y-6">

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
            <!-- Aplikasi / Sistem Tujuan -->
            <div>
                <label for="application_id" class="block text-sm font-semibold text-slate-700 mb-1">Sistem / Aplikasi
                    Target *</label>
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

            <!-- Target Tanggal Eksekusi -->
            <div>
                <label for="target_implementation_date" class="block text-sm font-semibold text-slate-700 mb-1">Target
                    Tanggal Pelaksanaan *</label>
                <input type="datetime-local" id="target_implementation_date" wire:model="target_implementation_date"
                    class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('target_implementation_date') ? 'border-red-500' : 'border-slate-300' }}">
                @error('target_implementation_date')
                    <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <!-- Checkbox & Input Surat Fisik -->
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
            <label class="flex items-center text-sm font-medium text-slate-700 cursor-pointer">
                <input type="checkbox" wire:model.live="is_physical_letter"
                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mr-2.5">
                Pengajuan Menggunakan Surat Fisik / Nota Dinas Official
            </label>

            @if ($is_physical_letter)
                <div class="pt-2">
                    <label for="physical_letter_number" class="block text-xs font-semibold text-slate-600 mb-1">Nomor
                        Surat Dinas / Nota Dinas *</label>
                    <input type="text" id="physical_letter_number" wire:model="physical_letter_number"
                        placeholder="Contoh: 500/012/DISKOMINFO/2026"
                        class="w-full text-sm px-3.5 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('physical_letter_number') ? 'border-red-500' : 'border-slate-300' }}">
                    @error('physical_letter_number')
                        <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
                    @enderror
                </div>
            @endif
        </div>

        <!-- Alasan Perubahan -->
        <div>
            <label for="reason_for_change" class="block text-sm font-semibold text-slate-700 mb-1">Alasan Perubahan
                (Reason for Change) *</label>
            <textarea id="reason_for_change" wire:model="reason_for_change" rows="3"
                placeholder="Jelaskan secara mendalam latar belakang perlunya perubahan sistem ini..."
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('reason_for_change') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('reason_for_change')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Deskripsi Dampak Sistem -->
        <div>
            <label for="impact_description" class="block text-sm font-semibold text-slate-700 mb-1">Estimasi Dampak
                Sistem (Impact Description) *</label>
            <textarea id="impact_description" wire:model="impact_description" rows="3"
                placeholder="Jelaskan estimasi downtime, jumlah pengguna terdampak, atau modul lain yang dipengaruhi..."
                class="w-full text-sm px-3.5 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 {{ $errors->has('impact_description') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
            @error('impact_description')
                <span class="text-xs text-red-500 block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Tombol Aksi Form -->
        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100">
            <button type="button" wire:click="saveChanges" wire:loading.attr="disabled"
                class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-lg text-sm transition disabled:opacity-60">
                Simpan Perubahan
            </button>

            <button type="button" wire:click="submit" wire:loading.attr="disabled"
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm shadow-sm transition disabled:opacity-60">
                {{ $isRevision ? 'Kirim Revisi' : 'Ajukan Sekarang' }}
            </button>
        </div>

    </form>
</div>
