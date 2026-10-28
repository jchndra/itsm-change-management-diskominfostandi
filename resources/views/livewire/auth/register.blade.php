<div class="mx-auto w-full max-w-[840px]">
    <div>
        <span
            class="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-[#1746d1]">
            <span class="h-1.5 w-1.5 rounded-full bg-[#1746d1]" aria-hidden="true"></span>
            Registrasi Pemohon
        </span>

        <h1 class="mt-4 text-[34px] font-bold leading-tight tracking-[-0.035em] text-slate-950 sm:text-[42px]">
            Buat Akun Pemohon
        </h1>
        <p class="mt-2 max-w-2xl text-[15px] leading-relaxed text-slate-500 sm:text-base">
            Daftarkan akun pegawai untuk mengajukan dan memantau Request for Change (RFC) pada layanan teknologi
            informasi Diskominfostandi Kota Bekasi.
        </p>
    </div>

    <section aria-labelledby="registration-steps-title"
        class="mt-6 rounded-2xl border border-blue-100/90 bg-white/75 p-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="registration-steps-title" class="text-[11px] font-bold uppercase tracking-[0.13em] text-slate-500">
                Alur Registrasi
            </h2>
            <p class="text-[11px] font-medium text-[#1746d1]">Akun aktif otomatis setelah data valid</p>
        </div>

        <ol class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <li class="flex min-w-0 items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[12px] font-bold text-[#1746d1] ring-1 ring-blue-100">1</span>
                <div class="min-w-0">
                    <p class="text-[12px] font-semibold text-slate-800">Isi Formulir</p>
                    <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">Lengkapi data identitas dan akun.</p>
                </div>
            </li>
            <li class="flex min-w-0 items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[12px] font-bold text-[#1746d1] ring-1 ring-blue-100">2</span>
                <div class="min-w-0">
                    <p class="text-[12px] font-semibold text-slate-800">Validasi Data</p>
                    <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">Sistem memeriksa kelengkapan dan
                        keunikan data.</p>
                </div>
            </li>
            <li class="flex min-w-0 items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[12px] font-bold text-[#1746d1] ring-1 ring-blue-100">3</span>
                <div class="min-w-0">
                    <p class="text-[12px] font-semibold text-slate-800">Akun Aktif</p>
                    <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">Akun Pemohon aktif otomatis setelah
                        validasi berhasil.</p>
                </div>
            </li>
            <li class="flex min-w-0 items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[12px] font-bold text-[#1746d1] ring-1 ring-blue-100">4</span>
                <div class="min-w-0">
                    <p class="text-[12px] font-semibold text-slate-800">Siap Digunakan</p>
                    <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">Anda langsung masuk dan dapat
                        mengajukan RFC.</p>
                </div>
            </li>
        </ol>
    </section>

    @if ($opds->isEmpty())
        <div role="alert"
            class="mt-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7"
                stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3.75m0 3h.008v.008H12v-.008ZM10.34 3.94 2.7 17.18a2.25 2.25 0 0 0 1.95 3.38h14.7a2.25 2.25 0 0 0 1.95-3.38L13.66 3.94a2.25 2.25 0 0 0-3.32 0Z" />
            </svg>
            <p>Belum ada OPD/unit kerja aktif. Registrasi belum dapat diselesaikan; silakan hubungi administrator.</p>
        </div>
    @endif

    <form wire:submit="register" class="mt-5 space-y-5">
        <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-[0_18px_45px_-36px_rgba(15,23,42,0.35)]"
            aria-labelledby="identity-section-title">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-7">
                <div>
                    <h2 id="identity-section-title" class="text-[16px] font-bold text-slate-900">Data Identitas</h2>
                    <p class="mt-1 text-[12px] leading-relaxed text-slate-500">
                        Isi sesuai data pegawai yang tercatat pada instansi.
                    </p>
                </div>
                <p class="text-[11px] font-medium text-slate-400">
                    <span class="text-rose-500" aria-hidden="true">*</span> Wajib diisi
                </p>
            </div>

            <div class="grid gap-x-5 gap-y-5 px-5 py-5 md:grid-cols-2 sm:px-7 sm:py-6">
                {{-- Nama lengkap --}}
                <div class="min-w-0">
                    <label for="name" class="block text-[13px] font-semibold text-slate-800">
                        Nama lengkap <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <div class="relative mt-2">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a8.25 8.25 0 1 1 15 0" />
                        </svg>
                        <input wire:model="name" id="name" type="text" required autofocus autocomplete="name"
                            placeholder="Nama sesuai identitas"
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                            @if ($errors->has('name')) aria-describedby="name-error" @endif
                            class="h-[52px] w-full rounded-xl border bg-slate-50/80 pl-11 pr-4 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('name') ? 'border-rose-400' : 'border-slate-200' }}">
                    </div>
                    @error('name')
                        <p id="name-error" role="alert" class="mt-2 text-[11px] font-medium text-rose-600">
                            {{ $message }}</p>
                    @enderror
                </div>

                {{-- NIP --}}
                <div class="min-w-0">
                    <div class="flex items-center justify-between gap-3">
                        <label for="nip" class="block text-[13px] font-semibold text-slate-800">
                            NIP <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <span
                            class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-slate-500">18
                            digit</span>
                    </div>
                    <div class="relative mt-2">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 4.5h15A2.25 2.25 0 0 1 21.75 6.75v10.5A2.25 2.25 0 0 1 19.5 19.5h-15a2.25 2.25 0 0 1-2.25-2.25V6.75A2.25 2.25 0 0 1 4.5 4.5Zm3 4.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                        </svg>
                        <input wire:model="nip" id="nip" type="text" inputmode="numeric" required
                            autocomplete="off" placeholder="18 digit angka" maxlength="18"
                            aria-invalid="{{ $errors->has('nip') ? 'true' : 'false' }}"
                            aria-describedby="nip-help{{ $errors->has('nip') ? ' nip-error' : '' }}"
                            class="h-[52px] w-full rounded-xl border bg-slate-50/80 pl-11 pr-4 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('nip') ? 'border-rose-400' : 'border-slate-200' }}">
                    </div>
                    <p id="nip-help" class="mt-1.5 text-[10.5px] leading-relaxed text-slate-400">
                        Masukkan 18 digit angka tanpa spasi.</p>
                    @error('nip')
                        <p id="nip-error" role="alert" class="mt-1.5 text-[11px] font-medium text-rose-600">
                            {{ $message }}</p>
                    @enderror
                </div>

                {{-- OPD --}}
                <div class="min-w-0">
                    <label for="opd_id" class="block text-[13px] font-semibold text-slate-800">
                        OPD/unit kerja <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <div class="relative mt-2">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 21h16.5M4.5 3h15A1.5 1.5 0 0 1 21 4.5v15H3v-15A1.5 1.5 0 0 1 4.5 3Zm4.5 5.25h.008v.008H9V8.25Zm0 4.5h.008v.008H9v-.008Zm0 4.5h.008v.008H9v-.008Zm6-9h.008v.008H15V8.25Zm0 4.5h.008v.008H15v-.008Zm0 4.5h.008v.008H15v-.008Z" />
                        </svg>
                        <select wire:model="opd_id" id="opd_id" required @disabled($opds->isEmpty())
                            aria-invalid="{{ $errors->has('opd_id') ? 'true' : 'false' }}"
                            @if ($errors->has('opd_id')) aria-describedby="opd-id-error" @endif
                            class="h-[52px] w-full appearance-none rounded-xl border bg-slate-50/80 pl-11 pr-10 text-[14px] text-slate-900 outline-none transition focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:text-slate-400 {{ $errors->has('opd_id') ? 'border-rose-400' : 'border-slate-200' }}">
                            <option value="">Pilih OPD/unit kerja</option>
                            @foreach ($opds as $opd)
                                <option value="{{ $opd->id }}">{{ $opd->name }}</option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                    @error('opd_id')
                        <p id="opd-id-error" role="alert" class="mt-2 text-[11px] font-medium text-rose-600">
                            {{ $message }}</p>
                    @enderror
                </div>

                {{-- Jabatan --}}
                <div class="min-w-0">
                    <label for="position" class="block text-[13px] font-semibold text-slate-800">
                        Jabatan <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <div class="relative mt-2">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M20.25 14.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-3.75m16.5 0V9a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 9v5.25m16.5 0H15a1.5 1.5 0 0 1-1.5-1.5v-.75h-3v.75a1.5 1.5 0 0 1-1.5 1.5H3.75M12 6.75V4.5m0 0h1.5M12 4.5h-1.5" />
                        </svg>
                        <input wire:model="position" id="position" type="text" required
                            autocomplete="organization-title" placeholder="Jabatan di unit kerja"
                            aria-invalid="{{ $errors->has('position') ? 'true' : 'false' }}"
                            @if ($errors->has('position')) aria-describedby="position-error" @endif
                            class="h-[52px] w-full rounded-xl border bg-slate-50/80 pl-11 pr-4 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('position') ? 'border-rose-400' : 'border-slate-200' }}">
                    </div>
                    @error('position')
                        <p id="position-error" role="alert" class="mt-2 text-[11px] font-medium text-rose-600">
                            {{ $message }}</p>
                    @enderror
                </div>

                {{-- Nomor telepon --}}
                <div class="min-w-0">
                    <label for="phone_number" class="block text-[13px] font-semibold text-slate-800">
                        Nomor telepon <span class="font-normal text-slate-400">(opsional)</span>
                    </label>
                    <div class="relative mt-2">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18h3" />
                        </svg>
                        <input wire:model="phone_number" id="phone_number" type="tel" autocomplete="tel"
                            placeholder="08xxxxxxxxxx"
                            aria-invalid="{{ $errors->has('phone_number') ? 'true' : 'false' }}"
                            @if ($errors->has('phone_number')) aria-describedby="phone-number-error" @endif
                            class="h-[52px] w-full rounded-xl border bg-slate-50/80 pl-11 pr-4 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('phone_number') ? 'border-rose-400' : 'border-slate-200' }}">
                    </div>
                    @error('phone_number')
                        <p id="phone-number-error" role="alert" class="mt-2 text-[11px] font-medium text-rose-600">
                            {{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div class="min-w-0">
                    <label for="email" class="block text-[13px] font-semibold text-slate-800">
                        Alamat email <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <div class="relative mt-2">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                        <input wire:model="email" id="email" type="email" required autocomplete="email"
                            placeholder="nama@instansi.go.id"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            @if ($errors->has('email')) aria-describedby="email-error" @endif
                            class="h-[52px] w-full rounded-xl border bg-slate-50/80 pl-11 pr-4 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('email') ? 'border-rose-400' : 'border-slate-200' }}">
                    </div>
                    @error('email')
                        <p id="email-error" role="alert" class="mt-2 text-[11px] font-medium text-rose-600">
                            {{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-[0_18px_45px_-36px_rgba(15,23,42,0.35)]"
            aria-labelledby="security-section-title">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-7">
                <div>
                    <h2 id="security-section-title" class="text-[16px] font-bold text-slate-900">Keamanan Akun</h2>
                    <p class="mt-1 text-[12px] leading-relaxed text-slate-500">
                        Gunakan kata sandi yang kuat dan mudah Anda ingat.
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.7"
                        stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    Minimal 8 karakter
                </span>
            </div>

            <div class="grid gap-5 px-5 py-5 md:grid-cols-2 sm:px-7 sm:py-6">
                {{-- Kata sandi --}}
                <div class="min-w-0" x-data="{
                    show: false,
                    password: '',
                    score: 0,
                    label: 'Minimal 8 karakter',
                    evaluate(value) {
                        this.password = value;
                        let score = 0;

                        if (value.length >= 8) {
                            score = 1;

                            if (/[a-z]/.test(value) && /[A-Z]/.test(value)) {
                                score++;
                            }

                            if (/\d/.test(value)) {
                                score++;
                            }

                            if (/[^A-Za-z0-9]/.test(value)) {
                                score++;
                            }
                        }

                        this.score = score;
                        this.label = value.length === 0 ?
                            'Minimal 8 karakter' :
                            (value.length < 8 ? 'Kurang dari 8 karakter' : ['', 'Lemah', 'Sedang', 'Kuat', 'Sangat kuat'][score]);
                    },
                    barClass(index) {
                        if (this.score < index) {
                            return 'bg-slate-200';
                        }

                        return ['', 'bg-rose-500', 'bg-amber-400', 'bg-sky-500', 'bg-emerald-500'][this.score];
                    },
                }" x-init="$nextTick(() => evaluate($refs.password.value))">
                    <label for="password" class="block text-[13px] font-semibold text-slate-800">
                        Kata sandi <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <div class="relative mt-2">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <input wire:model="password" x-ref="password" x-on:input="evaluate($event.target.value)"
                            id="password" x-bind:type="show ? 'text' : 'password'" required
                            autocomplete="new-password" placeholder="Buat kata sandi"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            aria-describedby="password-strength{{ $errors->has('password') ? ' password-error' : '' }}"
                            class="h-[52px] w-full rounded-xl border bg-slate-50/80 pl-11 pr-12 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('password') ? 'border-rose-400' : 'border-slate-200' }}">
                        <button type="button" x-on:click="show = !show" x-bind:aria-pressed="show"
                            x-bind:aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                            x-bind:title="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                            aria-controls="password"
                            class="absolute inset-y-0 right-2 my-auto flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1746d1] focus-visible:ring-offset-2">
                            <svg x-show="!show" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <svg x-show="show" x-cloak class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-2.5">
                        <div class="grid grid-cols-4 gap-1.5" aria-hidden="true">
                            <span class="h-1 rounded-full transition-colors duration-200"
                                x-bind:class="barClass(1)"></span>
                            <span class="h-1 rounded-full transition-colors duration-200"
                                x-bind:class="barClass(2)"></span>
                            <span class="h-1 rounded-full transition-colors duration-200"
                                x-bind:class="barClass(3)"></span>
                            <span class="h-1 rounded-full transition-colors duration-200"
                                x-bind:class="barClass(4)"></span>
                        </div>
                        <p id="password-strength" aria-live="polite"
                            class="mt-1.5 text-[10.5px] font-medium text-slate-400"
                            x-bind:class="score === 0 ? (password.length ? 'text-rose-600' : 'text-slate-400') : (score === 1 ?
                                'text-rose-600' : score === 2 ? 'text-amber-600' : score === 3 ?
                                'text-sky-700' : 'text-emerald-700')"
                            x-text="label">Minimal 8 karakter</p>
                    </div>

                    @error('password')
                        <p id="password-error" role="alert" class="mt-2 text-[11px] font-medium text-rose-600">
                            {{ $message }}</p>
                    @enderror
                </div>

                {{-- Konfirmasi kata sandi --}}
                <div class="min-w-0" x-data="{ show: false }">
                    <label for="password_confirmation" class="block text-[13px] font-semibold text-slate-800">
                        Konfirmasi kata sandi <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <div class="relative mt-2">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <input wire:model="password_confirmation" id="password_confirmation"
                            x-bind:type="show ? 'text' : 'password'" required autocomplete="new-password"
                            placeholder="Ulangi kata sandi"
                            aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}"
                            @if ($errors->has('password_confirmation')) aria-describedby="password-confirmation-error" @endif
                            class="h-[52px] w-full rounded-xl border bg-slate-50/80 pl-11 pr-12 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('password_confirmation') ? 'border-rose-400' : 'border-slate-200' }}">
                        <button type="button" x-on:click="show = !show" x-bind:aria-pressed="show"
                            x-bind:aria-label="show ? 'Sembunyikan konfirmasi kata sandi' :
                                'Tampilkan konfirmasi kata sandi'"
                            x-bind:title="show ? 'Sembunyikan konfirmasi kata sandi' :
                                'Tampilkan konfirmasi kata sandi'"
                            aria-controls="password_confirmation"
                            class="absolute inset-y-0 right-2 my-auto flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1746d1] focus-visible:ring-offset-2">
                            <svg x-show="!show" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <svg x-show="show" x-cloak class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    @error('password_confirmation')
                        <p id="password-confirmation-error" role="alert"
                            class="mt-2 text-[11px] font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <aside class="flex items-start gap-3.5 rounded-2xl border border-blue-100 bg-[#f2f7ff] p-4 sm:p-5"
            aria-labelledby="data-protection-title">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-[#1746d1] shadow-sm ring-1 ring-blue-100">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
            </div>
            <div>
                <h2 id="data-protection-title" class="text-[13px] font-bold text-slate-800">Perlindungan Data Pemohon
                </h2>
                <p class="mt-1 text-[11.5px] leading-relaxed text-slate-600">
                    Data identitas dan akun digunakan untuk verifikasi pegawai, pengelolaan permohonan perubahan layanan
                    TI, serta penyampaian notifikasi terkait permohonan Anda.
                </p>
            </div>
        </aside>

        <div>
            <button type="submit" wire:loading.attr="disabled" wire:target="register" @disabled($opds->isEmpty())
                class="group flex h-[54px] w-full items-center justify-center gap-2 rounded-xl bg-[#1746d1] px-6 text-[15px] font-semibold text-white shadow-[0_12px_28px_-14px_rgba(23,70,209,0.85)] transition duration-150 hover:bg-[#123ab0] focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200 disabled:cursor-not-allowed disabled:opacity-60">
                <svg wire:loading wire:target="register" class="h-4 w-4 animate-spin" fill="none"
                    viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                </svg>
                <svg wire:loading.remove wire:target="register" class="h-[18px] w-[18px]" fill="none"
                    viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                </svg>
                <span wire:loading.remove wire:target="register">Daftar sebagai Pemohon</span>
                <span wire:loading wire:target="register">Memproses...</span>
                <svg wire:loading.remove wire:target="register"
                    class="h-4 w-4 transition-transform duration-150 group-hover:translate-x-0.5" fill="none"
                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </button>
            <p class="mt-3 text-center text-[11px] leading-relaxed text-slate-500">
                Pastikan seluruh data yang Anda isi sudah benar sebelum mendaftar.
            </p>
        </div>
    </form>
</div>
