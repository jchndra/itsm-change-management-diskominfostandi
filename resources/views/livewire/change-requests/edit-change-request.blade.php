@php
    $inputBase = 'w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30';
@endphp

<div class="mx-auto max-w-5xl space-y-6">
    {{-- Header Halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex min-w-0 items-start gap-3 sm:gap-4">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-600/25">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                </svg>
            </span>
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-blue-700">Permohonan Perubahan</p>
                <h1 class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-2 text-[26px] font-bold leading-tight tracking-tight text-slate-900 sm:text-[30px]">
                    {{ $isRevision ? 'Revisi RFC' : 'Edit RFC' }}
                    <span class="text-slate-400">{{ $rfc->displayIdentifier() }}</span>
                    <span @class([
                        'inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wide ring-1 ring-inset',
                        'bg-amber-50 text-amber-700 ring-amber-200' => $isRevision,
                        'bg-slate-100 text-slate-600 ring-slate-200' => ! $isRevision,
                    ])>
                        {{ $isRevision ? 'Perlu Revisi' : 'Draft' }}
                    </span>
                </h1>
                <p class="mt-1 max-w-xl text-sm leading-relaxed text-slate-500">
                    {{ $isRevision
                        ? 'Perbaiki data RFC sesuai catatan Change Manager, lalu kirim revisi.'
                        : 'Perbarui data RFC sebelum mengajukannya.' }}
                </p>
            </div>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2 self-start">
            <livewire:change-requests.cancel-rfc :rfc="$rfc" />
            <a href="{{ route('change-requests.show', $rfc) }}" wire:navigate
                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Batal
            </a>
        </div>
    </div>

    @if ($isRevision && filled($revisionReason))
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-5" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 3.94 2.7 17.06A1.9 1.9 0 0 0 4.34 20h15.32a1.9 1.9 0 0 0 1.64-2.9L13.66 3.94a1.9 1.9 0 0 0-3.32 0Z" />
            </svg>
            <div class="min-w-0">
                <h2 class="text-sm font-bold text-amber-900">Alasan Permintaan Revisi dari Change Manager</h2>
                <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-amber-800">{{ $revisionReason }}</p>
            </div>
        </div>
    @endif

    @error('status')
        <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 3.94 2.7 17.06A1.9 1.9 0 0 0 4.34 20h15.32a1.9 1.9 0 0 0 1.64-2.9L13.66 3.94a1.9 1.9 0 0 0-3.32 0Z" />
            </svg>
            <span>{{ $message }}</span>
        </div>
    @enderror

    {{-- Form Container --}}
    <form class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="space-y-8 p-5 sm:p-7 lg:p-8">

            {{-- 1. Data Pemohon --}}
            <section aria-labelledby="section-pemohon-heading">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-blue-600 text-[13px] font-bold text-white">1</span>
                        <div>
                            <h2 id="section-pemohon-heading" class="text-sm font-bold uppercase tracking-wide text-slate-800">Data Pemohon</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Identitas pengaju perubahan.</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-600">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        Terisi otomatis
                    </span>
                </div>

                @if (blank($requester?->position) || blank($requester?->phone_number))
                    <div class="mt-4 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                        role="alert">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 3.94 2.7 17.06A1.9 1.9 0 0 0 4.34 20h15.32a1.9 1.9 0 0 0 1.64-2.9L13.66 3.94a1.9 1.9 0 0 0-3.32 0Z" />
                        </svg>
                        <span>
                            Jabatan atau no. telepon belum lengkap.
                            <a href="{{ route('profile.show') }}" wire:navigate class="font-semibold underline underline-offset-2 hover:text-amber-900">
                                Lengkapi profil
                            </a>
                            agar data pemohon pada formulir ini lengkap.
                        </span>
                    </div>
                @endif

                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2 sm:p-5">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Nama</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ $requester?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Jabatan</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ $requester?->position ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Unit Kerja (OPD)</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ $requester?->opd?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">No. Telepon</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ $requester?->phone_number ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Email</dt>
                        <dd class="mt-1 font-medium text-slate-800 break-all">{{ $requester?->email ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">NIP</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ $requester?->nip ?: '-' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- 2. Informasi Perubahan --}}
            <section aria-labelledby="section-informasi-heading" class="border-t border-slate-100 pt-7">
                <div class="flex items-center gap-3">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-blue-600 text-[13px] font-bold text-white">2</span>
                    <div>
                        <h2 id="section-informasi-heading" class="text-sm font-bold uppercase tracking-wide text-slate-800">Informasi Perubahan</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Judul, kategori, dan sistem yang terdampak.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-5">
                    {{-- Judul Perubahan --}}
                    <div>
                        <label for="title" class="mb-1.5 block text-sm font-semibold text-slate-700">Judul Perubahan <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <input type="text" id="title" wire:model="title"
                            placeholder="Contoh: Pembaruan Modul Integrasi Database API"
                            @error('title') aria-invalid="true" aria-describedby="title-error" @enderror
                            class="{{ $inputBase }} {{ $errors->has('title') ? 'border-rose-400' : 'border-slate-300' }}">
                        @error('title')
                            <p id="title-error" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-rose-600" role="alert">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-18 10.5 18H1.5L12 3.75Z" />
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        {{-- Kategori Perubahan --}}
                        <div>
                            <label for="change_type" class="mb-1.5 block text-sm font-semibold text-slate-700">Kategori Perubahan <span class="text-rose-600" aria-hidden="true">*</span></label>
                            <select id="change_type" wire:model="change_type"
                                @error('change_type') aria-invalid="true" aria-describedby="change-type-error" @enderror
                                class="{{ $inputBase }} {{ $errors->has('change_type') ? 'border-rose-400' : 'border-slate-300' }}">
                                <option value="">-- Pilih Kategori --</option>
                                <option value="standard">Standar</option>
                                <option value="normal">Normal</option>
                                <option value="emergency">Emergency</option>
                            </select>
                            @error('change_type')
                                <p id="change-type-error" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-rose-600" role="alert">
                                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-18 10.5 18H1.5L12 3.75Z" />
                                    </svg>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        {{-- Aplikasi / Sistem Tujuan --}}
                        <div>
                            <label for="application_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Sistem / Layanan yang Terdampak <span class="text-rose-600" aria-hidden="true">*</span></label>
                            <select id="application_id" wire:model="application_id"
                                @error('application_id') aria-invalid="true" aria-describedby="application-id-error" @enderror
                                class="{{ $inputBase }} {{ $errors->has('application_id') ? 'border-rose-400' : 'border-slate-300' }}">
                                <option value="">-- Pilih Aplikasi --</option>
                                @foreach ($applications as $app)
                                    <option value="{{ $app->id }}">
                                        {{ $app->name }} ({{ $app->opd->code ?? 'OPD' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('application_id')
                                <p id="application-id-error" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-rose-600" role="alert">
                                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-18 10.5 18H1.5L12 3.75Z" />
                                    </svg>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>
            </section>

            {{-- 3. Detail Perubahan --}}
            <section aria-labelledby="section-detail-heading" class="border-t border-slate-100 pt-7">
                <div class="flex items-center gap-3">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-blue-600 text-[13px] font-bold text-white">3</span>
                    <div>
                        <h2 id="section-detail-heading" class="text-sm font-bold uppercase tracking-wide text-slate-800">Detail Perubahan</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Konteks, urgensi, dan dasar hukum perubahan.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-5">
                    {{-- Detail Perubahan --}}
                    <div>
                        <label for="description" class="mb-1.5 block text-sm font-semibold text-slate-700">Detail Perubahan <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <textarea id="description" wire:model="description" rows="4"
                            placeholder="Jelaskan perubahan yang diajukan beserta tujuan yang ingin dicapai..."
                            @error('description') aria-invalid="true" aria-describedby="description-error" @enderror
                            class="{{ $inputBase }} {{ $errors->has('description') ? 'border-rose-400' : 'border-slate-300' }}"></textarea>
                        @error('description')
                            <p id="description-error" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-rose-600" role="alert">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-18 10.5 18H1.5L12 3.75Z" />
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    {{-- Alasan Perubahan --}}
                    <div>
                        <label for="reason_for_change" class="mb-1.5 block text-sm font-semibold text-slate-700">Alasan Perubahan <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <textarea id="reason_for_change" wire:model="reason_for_change" rows="3"
                            placeholder="Jelaskan latar belakang dan urgensi perubahan ini..."
                            @error('reason_for_change') aria-invalid="true" aria-describedby="reason-for-change-error" @enderror
                            class="{{ $inputBase }} {{ $errors->has('reason_for_change') ? 'border-rose-400' : 'border-slate-300' }}"></textarea>
                        <p class="mt-1.5 text-[11px] text-slate-500">Minimal 10 karakter.</p>
                        @error('reason_for_change')
                            <p id="reason-for-change-error" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-rose-600" role="alert">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-18 10.5 18H1.5L12 3.75Z" />
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    {{-- Dasar Hukum --}}
                    <div>
                        <label for="legal_basis" class="mb-1.5 block text-sm font-semibold text-slate-700">Dasar Hukum <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <textarea id="legal_basis" wire:model="legal_basis" rows="3"
                            placeholder="Sebutkan dasar hukum atau regulasi yang melandasi perubahan ini..."
                            @error('legal_basis') aria-invalid="true" aria-describedby="legal-basis-error" @enderror
                            class="{{ $inputBase }} {{ $errors->has('legal_basis') ? 'border-rose-400' : 'border-slate-300' }}"></textarea>
                        @error('legal_basis')
                            <p id="legal-basis-error" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-rose-600" role="alert">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-18 10.5 18H1.5L12 3.75Z" />
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>
                </div>
            </section>

            {{-- 4. Risiko & Dampak --}}
            <section aria-labelledby="section-risiko-heading" class="border-t border-slate-100 pt-7">
                <div class="flex items-center gap-3">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-blue-600 text-[13px] font-bold text-white">4</span>
                    <div>
                        <h2 id="section-risiko-heading" class="text-sm font-bold uppercase tracking-wide text-slate-800">Risiko &amp; Dampak</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Identifikasi risiko dan estimasi besaran dampaknya.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-5">
                    {{-- Risiko Perubahan --}}
                    <div>
                        <label for="change_risk" class="mb-1.5 block text-sm font-semibold text-slate-700">Risiko Perubahan <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <textarea id="change_risk" wire:model="change_risk" rows="3"
                            placeholder="Jelaskan risiko yang mungkin timbul jika perubahan dilakukan atau tidak dilakukan..."
                            @error('change_risk') aria-invalid="true" aria-describedby="change-risk-error" @enderror
                            class="{{ $inputBase }} {{ $errors->has('change_risk') ? 'border-rose-400' : 'border-slate-300' }}"></textarea>
                        @error('change_risk')
                            <p id="change-risk-error" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-rose-600" role="alert">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-18 10.5 18H1.5L12 3.75Z" />
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    {{-- Register Risiko --}}
                    <div>
                        <label for="risk_event" class="mb-1.5 block text-sm font-semibold text-slate-700">Register Risiko</label>
                        <select id="risk_event" wire:model="risk_event"
                            @error('risk_event') aria-invalid="true" aria-describedby="risk-event-error" @enderror
                            class="{{ $inputBase }} {{ $errors->has('risk_event') ? 'border-rose-400' : 'border-slate-300' }}">
                            <option value="">-- Pilih Register Risiko --</option>
                            @foreach (\App\Enums\RiskEvent::cases() as $riskEvent)
                                <option value="{{ $riskEvent->value }}">{{ $riskEvent->optionLabel() }}</option>
                            @endforeach
                        </select>
                        @include('livewire.change-requests.partials.risk-register-reference')
                        @error('risk_event')
                            <p id="risk-event-error" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-rose-600" role="alert">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-18 10.5 18H1.5L12 3.75Z" />
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    {{-- Analisis Dampak dan Risiko Perubahan --}}
                    @include('livewire.change-requests.partials.risk-calculator')

                    {{-- Keterangan --}}
                    <div>
                        <label for="impact_description" class="mb-1.5 block text-sm font-semibold text-slate-700">Keterangan
                            <span class="font-normal text-slate-400">(opsional)</span></label>
                        <textarea id="impact_description" wire:model="impact_description" rows="3"
                            placeholder="Tuliskan keterangan tambahan terkait perubahan, misalnya perkiraan downtime, jumlah pengguna terdampak, atau modul lain yang dipengaruhi..."
                            @error('impact_description') aria-invalid="true" aria-describedby="impact-description-error" @enderror
                            class="{{ $inputBase }} {{ $errors->has('impact_description') ? 'border-rose-400' : 'border-slate-300' }}"></textarea>
                        @error('impact_description')
                            <p id="impact-description-error" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-rose-600" role="alert">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-18 10.5 18H1.5L12 3.75Z" />
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>
                </div>
            </section>

            {{-- 5. Lampiran & Pernyataan --}}
            <section aria-labelledby="section-lampiran-heading" class="border-t border-slate-100 pt-7">
                <div class="flex items-center gap-3">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-blue-600 text-[13px] font-bold text-white">5</span>
                    <div>
                        <h2 id="section-lampiran-heading" class="text-sm font-bold uppercase tracking-wide text-slate-800">Lampiran &amp; Pernyataan</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Berkas pendukung dan pernyataan persetujuan pimpinan.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-5">
                    {{-- Lampiran --}}
                    <div class="space-y-5">
                        <div>
                            <label for="attachments" class="mb-1.5 block text-sm font-semibold text-slate-700">Lampiran <span class="text-rose-600" aria-hidden="true">*</span></label>
                            <p class="text-xs text-slate-500">Berkas pendukung lainnya. Format PDF maksimal 5 file dan 5 MB per file.</p>
                        </div>

                        @if ($rfc->attachments->isNotEmpty())
                            <div class="space-y-2">
                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Lampiran tersimpan</p>
                                <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                                    @foreach ($rfc->attachments as $attachment)
                                        <li class="flex flex-wrap items-center justify-between gap-2 bg-white px-3 py-2.5 text-sm"
                                            wire:key="stored-attachment-{{ $attachment->id }}">
                                            <div class="flex min-w-0 flex-1 items-center gap-2.5">
                                                <svg class="h-7 w-7 shrink-0 text-rose-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                    <path d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6Z" opacity="0.18" />
                                                    <path fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" d="M13 2 19 8v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h6Z" />
                                                    <path fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" d="M13 2v6h6" />
                                                </svg>
                                                <div class="min-w-0">
                                                    <p class="truncate font-medium text-slate-700">{{ $attachment->original_name }}</p>
                                                    <p class="text-xs text-slate-400">{{ number_format($attachment->size / 1024, 0, ',', '.') }} KB</p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-1 text-xs font-semibold">
                                                <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]) }}"
                                                    target="_blank" rel="noopener"
                                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-blue-700 transition hover:bg-blue-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                    Lihat
                                                </a>
                                                <a href="{{ route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment, 'download' => 1]) }}"
                                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-blue-700 transition hover:bg-blue-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                                    </svg>
                                                    Unduh
                                                </a>
                                                @unless ($attachment->isApprovalLetter())
                                                    <button type="button" wire:click="deleteAttachment({{ $attachment->id }})"
                                                        wire:confirm="Hapus lampiran ini?"
                                                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                        </svg>
                                                        Hapus
                                                    </button>
                                                @endunless
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Area unggah --}}
                        <div x-data="{ attachmentTypeError: '' }"
                            x-on:change.capture="
                                const files = Array.from($event.target.files || []);

                                if (files.some((file) => !file.name.toLowerCase().endsWith('.pdf'))) {
                                    $event.target.value = '';
                                    $event.stopPropagation();
                                    attachmentTypeError = 'Lampiran harus berformat PDF. Pilih ulang berkas PDF.';
                                } else {
                                    attachmentTypeError = '';
                                }
                            ">
                            <p class="mb-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Unggah lampiran</p>
                            <label for="attachments"
                                class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/50 px-6 py-7 text-center transition hover:border-blue-400 hover:bg-blue-50/40 focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-500/30">
                                <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                </svg>
                                <span class="text-sm font-semibold text-slate-700">Pilih berkas atau seret ke sini</span>
                                <span class="text-xs text-slate-500">Format PDF, maksimal 5 MB per file, 5 file per RFC</span>
                            </label>
                            <input type="file" id="attachments" wire:model="attachments" multiple
                                accept="application/pdf,.pdf" class="sr-only"
                                @error('attachments') aria-invalid="true" aria-describedby="attachments-error" @enderror>

                            <div wire:loading wire:target="attachments" class="mt-2 flex items-center gap-2 text-xs font-medium text-blue-700">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                                </svg>
                                Mengunggah lampiran...
                            </div>

                            @error('attachments')
                                <p id="attachments-error" class="mt-2 flex items-start gap-1.5 text-xs font-medium text-rose-600" role="alert">
                                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-18 10.5 18H1.5L12 3.75Z" />
                                    </svg>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror

                            <p x-show="attachmentTypeError" x-cloak x-text="attachmentTypeError"
                                class="mt-2 flex items-start gap-1.5 text-xs font-medium text-rose-600" role="alert"></p>
                        </div>

                        @if (count($attachments) > 0)
                            <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                                @foreach ($attachments as $index => $file)
                                    <li class="flex flex-wrap items-center justify-between gap-2 bg-white px-3 py-2.5 text-sm"
                                        wire:key="pending-attachment-{{ $index }}-{{ $file->getFilename() }}">
                                        <span class="flex min-w-0 flex-1 items-center gap-2.5">
                                            <svg class="h-7 w-7 shrink-0 text-rose-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <path d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6Z" opacity="0.18" />
                                                <path fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" d="M13 2 19 8v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h6Z" />
                                                <path fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" d="M13 2v6h6" />
                                            </svg>
                                            <span class="min-w-0 flex-1 truncate font-medium text-slate-700">{{ $file->getClientOriginalName() }}</span>
                                        </span>
                                        <span class="text-xs text-slate-400">{{ number_format($file->getSize() / 1024, 0, ',', '.') }} KB</span>
                                        <button type="button" wire:click="removePendingAttachment({{ $index }})"
                                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                            Hapus
                                        </button>
                                        @error('attachments.'.$index)
                                            <span class="w-full text-xs font-medium text-rose-600" role="alert">{{ $message }}</span>
                                        @enderror
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Pernyataan Persetujuan Pimpinan (Eselon II) --}}
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" wire:model.live="head_of_department_approved"
                                class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm leading-relaxed text-slate-700">Saya menyatakan bahwa perubahan ini telah disetujui oleh Pimpinan (Eselon II).</span>
                        </label>
                        @error('head_of_department_approved')
                            <p id="head-of-department-approved-error" class="mt-2 flex items-start gap-1.5 pl-7 text-xs font-medium text-rose-600" role="alert">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm0-18 10.5 18H1.5L12 3.75Z" />
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>
                </div>
            </section>
        </div>

        {{-- Tombol Aksi Form (sticky) --}}
        <div class="sticky bottom-0 z-10 border-t border-slate-200 bg-white/85 px-5 py-4 backdrop-blur sm:px-7 lg:px-8">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs leading-relaxed text-slate-500">
                    {{ $isRevision
                        ? 'Revisi yang dikirim akan menggantikan data RFC saat ini dan diteruskan ke Change Manager.'
                        : 'Perubahan akan menggantikan data RFC saat ini.' }}
                </p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
                    <button type="button" wire:click="saveChanges" wire:loading.attr="disabled" wire:target="saveChanges"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-100 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 disabled:cursor-not-allowed disabled:opacity-60 sm:min-h-0">
                        <svg wire:loading wire:target="saveChanges" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                        </svg>
                        Simpan Perubahan
                    </button>

                    <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:min-h-0">
                        <svg wire:loading wire:target="submit" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                        </svg>
                        {{ $isRevision ? 'Kirim Revisi' : 'Ajukan Sekarang' }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
