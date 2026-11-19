{{-- Matrix Analisis Dampak & Risiko.
     Perhitungan murni Alpine (client-side) dan nilai tidak tersimpan ke database.
     Dipakai bersama form Ajukan dan Edit/Revisi RFC agar tampilannya identik. --}}
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    aria-labelledby="risk-analysis-heading"
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
        panduanOpen: false,
    }"
    x-on:keydown.escape.window="panduanOpen = false"
    x-effect="document.body.style.overflow = panduanOpen ? 'hidden' : ''">

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-4 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </span>
            <div class="min-w-0">
                <h3 id="risk-analysis-heading" class="text-sm font-bold text-slate-900">
                    Analisis Dampak dan Risiko Perubahan
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">Besaran = 26 &minus; Prioritas. Isi Level Dampak dan Level Kemungkinan untuk melihat hasil.</p>
            </div>
        </div>

        <button type="button" @click="panduanOpen = true"
            class="inline-flex items-center gap-1.5 rounded-lg text-xs font-semibold text-blue-700 transition hover:text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            Panduan Pengisian Analisis Risiko
        </button>
    </div>

    <div class="grid grid-cols-1 gap-5 p-5 sm:p-6 lg:grid-cols-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:col-span-3">
            {{-- Level Dampak --}}
            <div>
                <label for="level_dampak" class="mb-1.5 block text-sm font-semibold text-slate-700">Level Dampak</label>
                <select id="level_dampak" name="level_dampak" x-model="dampak" x-on:change="hitung()"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                    <option value="">-- Pilih Level Dampak --</option>
                    <option value="1">Tidak Signifikan (1)</option>
                    <option value="2">Minor (2)</option>
                    <option value="3">Moderat (3)</option>
                    <option value="4">Signifikan (4)</option>
                    <option value="5">Sangat Signifikan (5)</option>
                </select>
            </div>

            {{-- Level Kemungkinan --}}
            <div>
                <label for="level_kemungkinan" class="mb-1.5 block text-sm font-semibold text-slate-700">Level Kemungkinan</label>
                <select id="level_kemungkinan" name="level_kemungkinan" x-model="kemungkinan" x-on:change="hitung()"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                    <option value="">-- Pilih Level Kemungkinan --</option>
                    <option value="1">Hampir Tidak Terjadi (1)</option>
                    <option value="2">Jarang Terjadi (2)</option>
                    <option value="3">Kadang Terjadi (3)</option>
                    <option value="4">Sering Terjadi (4)</option>
                    <option value="5">Hampir Pasti Terjadi (5)</option>
                </select>
            </div>

            <p class="text-xs leading-relaxed text-slate-500 sm:col-span-2">
                Gunakan tabel B.1 “Register Risiko Indikatif Tingkat Kota” pada Panduan Manajemen Risiko untuk
                menentukan tingkat kemungkinan dan dampak.
            </p>
        </div>

        {{-- Hasil perhitungan --}}
        <div class="lg:col-span-2" aria-live="polite">
            <div class="flex h-full flex-col justify-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-center sm:p-5">
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Hasil Perhitungan</p>
                <div class="grid grid-cols-2 items-center gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Besaran Risiko</p>
                        <p class="mt-1 text-4xl font-extrabold leading-none tracking-tight text-slate-900"
                            x-text="besaran || '—'">—</p>
                        <p class="mt-1 text-[11px] text-slate-400">skala 1–25</p>
                    </div>
                    <div class="border-l border-slate-200 pl-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Level Risiko</p>
                        <span x-text="level ? ({ 1: 'Sangat Rendah (1)', 2: 'Rendah (2)', 3: 'Sedang (3)', 4: 'Tinggi (4)', 5: 'Sangat Tinggi (5)' }[Number(level)]) : 'Belum dipilih'"
                            x-bind:class="level === '5' ? 'bg-rose-100 text-rose-800 ring-rose-200'
                                : level === '4' ? 'bg-amber-100 text-amber-800 ring-amber-300'
                                : level === '3' ? 'bg-yellow-100 text-yellow-800 ring-yellow-200'
                                : (level === '2' || level === '1') ? 'bg-emerald-100 text-emerald-800 ring-emerald-200'
                                : 'bg-slate-100 text-slate-500 ring-slate-200'"
                            class="mt-1.5 inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold ring-1 ring-inset">Belum dipilih</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pratinjau Panduan Pengisian Analisis Risiko --}}
    <div x-show="panduanOpen" x-cloak x-transition.opacity.duration.150ms
        class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 backdrop-blur-sm sm:p-4"
        role="dialog" aria-modal="true" aria-labelledby="panduan-analisis-risiko-title"
        @click.self="panduanOpen = false">
        <div
            class="flex h-full w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[90vh] sm:max-w-4xl sm:rounded-2xl">
            <div class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </span>

                <div class="min-w-0 flex-1">
                    <p id="panduan-analisis-risiko-title" class="truncate text-sm font-semibold text-slate-800">
                        Panduan Pengisian Analisis Risiko
                    </p>
                    <p class="text-[11px] text-slate-400">Pratinjau dokumen PDF</p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ route('rfc-documents.panduan-analisis-risiko') }}" target="_blank" rel="noopener"
                        class="hidden rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 sm:inline-flex">
                        Buka di tab baru
                    </a>
                    <button type="button" @click="panduanOpen = false" aria-label="Tutup pratinjau panduan"
                        class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 bg-slate-100 sm:flex-none">
                <template x-if="panduanOpen">
                    <iframe src="{{ route('rfc-documents.panduan-analisis-risiko') }}"
                        title="Panduan Pengisian Analisis Risiko"
                        class="h-[calc(100dvh-4.25rem)] w-full sm:h-[75vh]"></iframe>
                </template>
            </div>
        </div>
    </div>
</section>
