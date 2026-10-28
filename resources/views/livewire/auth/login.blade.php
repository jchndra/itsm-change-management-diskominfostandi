<div class="mx-auto w-full max-w-[480px]">
    <div>
        <span
            class="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-[#1746d1]">
            <span class="h-1.5 w-1.5 rounded-full bg-[#1746d1]" aria-hidden="true"></span>
            Portal Layanan Internal
        </span>

        <h1 class="mt-4 text-[36px] font-bold leading-tight tracking-[-0.035em] text-slate-950 sm:text-[44px]">
            Selamat datang
        </h1>
        <p class="mt-2 max-w-md text-[15px] leading-relaxed text-slate-500 sm:text-base">
            Masuk dengan akun pegawai untuk mengajukan dan memantau perubahan layanan TI.
        </p>
    </div>

    <form wire:submit="login" class="mt-7 sm:mt-8">
        <div
            class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-[0_22px_55px_-32px_rgba(15,23,42,0.35)] sm:p-7">
            <div class="space-y-5">
                <div>
                    <label for="email" class="block text-[13px] font-semibold text-slate-800">
                        Alamat email
                    </label>
                    <div class="relative mt-2">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                        <input wire:model="email" id="email" type="email" required autofocus autocomplete="username"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            @if ($errors->has('email')) aria-describedby="email-error" @endif
                            class="h-[54px] w-full rounded-xl border bg-slate-50/80 pl-11 pr-4 text-[15px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('email') ? 'border-rose-400' : 'border-slate-200' }}">
                    </div>
                    @error('email')
                        <p id="email-error" role="alert" class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div x-data="{ show: false }">
                    <label for="password" class="block text-[13px] font-semibold text-slate-800">
                        Kata sandi
                    </label>
                    <div class="relative mt-2">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <input wire:model="password" id="password" x-bind:type="show ? 'text' : 'password'" required
                            autocomplete="current-password"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            @if ($errors->has('password')) aria-describedby="password-error" @endif
                            class="h-[54px] w-full rounded-xl border bg-slate-50/80 pl-11 pr-12 text-[15px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('password') ? 'border-rose-400' : 'border-slate-200' }}">
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
                    @error('password')
                        <p id="password-error" role="alert" class="mt-2 text-xs font-medium text-rose-600">{{ $message }}
                        </p>
                    @enderror
                </div>

                <label for="remember"
                    class="flex w-fit cursor-pointer select-none items-center gap-2.5 text-[13px] text-slate-600">
                    <input wire:model="remember" id="remember" type="checkbox"
                        class="h-[18px] w-[18px] cursor-pointer rounded border-slate-300 accent-[#1746d1] focus:ring-2 focus:ring-[#1746d1]/30 focus:ring-offset-0">
                    <span>Ingat saya</span>
                </label>

                <button type="submit" wire:loading.attr="disabled" wire:target="login"
                    class="group flex h-[54px] w-full items-center justify-center gap-2 rounded-xl bg-[#1746d1] px-6 text-[15px] font-semibold text-white shadow-[0_10px_24px_-12px_rgba(23,70,209,0.8)] transition duration-150 hover:bg-[#123ab0] focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200 disabled:cursor-wait disabled:opacity-80">
                    <svg wire:loading wire:target="login" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="login">Masuk</span>
                    <span wire:loading wire:target="login">Memproses...</span>
                    <svg wire:loading.remove wire:target="login"
                        class="h-4 w-4 transition-transform duration-150 group-hover:translate-x-0.5" fill="none"
                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </button>
            </div>

            <div class="mt-5 flex items-start gap-2.5 border-t border-slate-100 pt-4 text-[11px] leading-relaxed text-slate-500">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6"
                    stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 3 4.5 6v5.25c0 4.28 2.89 8.25 7.5 9.75 4.61-1.5 7.5-5.47 7.5-9.75V6L12 3Z" />
                </svg>
                <p>Akses hanya untuk pegawai yang terdaftar pada layanan perubahan TI Diskominfostandi Kota Bekasi.</p>
            </div>
        </div>
    </form>
</div>
