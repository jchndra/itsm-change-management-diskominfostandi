{{-- Referensi Lampiran B: Register Risiko Indikatif Tingkat Kota (gambar).
     Dipakai bersama form Ajukan dan Edit/Revisi RFC agar tautan selalu tampil
     sebagai pratinjau modal, bukan tab baru. --}}
<div x-data="{ registerDocOpen: false }"
    x-on:keydown.escape.window="registerDocOpen = false"
    x-effect="document.body.style.overflow = registerDocOpen ? 'hidden' : ''">
    <p class="mt-1.5 text-xs leading-relaxed text-slate-500">
        Pilih kejadian risiko pada gambar Panduan Manajemen Risiko
        <button type="button" @click="registerDocOpen = true"
            class="rounded font-semibold text-blue-700 hover:text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
            Lampiran B — Register Risiko Indikatif Tingkat Kota (Tabel B.1)
        </button>.
    </p>

    {{-- Pratinjau gambar register risiko --}}
    <div x-show="registerDocOpen" x-cloak x-transition.opacity.duration.150ms
        class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 backdrop-blur-sm sm:p-4"
        role="dialog" aria-modal="true" aria-labelledby="register-risiko-title"
        @click.self="registerDocOpen = false">
        <div
            class="flex h-full w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[90vh] sm:max-w-5xl sm:rounded-2xl">
            <div class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                            d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                </span>

                <div class="min-w-0 flex-1">
                    <p id="register-risiko-title" class="truncate text-sm font-semibold text-slate-800">
                        Lampiran B — Register Risiko Indikatif Tingkat Kota (Tabel B.1)
                    </p>
                    <p class="text-[11px] text-slate-400">Pratinjau gambar register risiko</p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ asset('images/register-risiko-indikatif.png') }}" target="_blank" rel="noopener"
                        class="hidden rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 sm:inline-flex">
                        Buka di tab baru
                    </a>
                    <a href="{{ asset('images/register-risiko-indikatif.png') }}" download
                        class="inline-flex rounded-lg border border-transparent bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700">
                        Unduh
                    </a>
                    <button type="button" @click="registerDocOpen = false" aria-label="Tutup pratinjau register risiko"
                        class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-auto bg-slate-100 p-3 sm:max-h-[75vh] sm:p-4">
                <template x-if="registerDocOpen">
                    <img src="{{ asset('images/register-risiko-indikatif.png') }}"
                        alt="Lampiran B — Register Risiko Indikatif Tingkat Kota (Tabel B.1)"
                        class="mx-auto h-auto w-auto max-w-full rounded-lg bg-white shadow-sm">
                </template>
            </div>
        </div>
    </div>
</div>
