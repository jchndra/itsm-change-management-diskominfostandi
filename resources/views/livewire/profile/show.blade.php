<div class="space-y-6" x-data="{ tab: 'profil' }">

    <div>
        <h1 class="text-[26px] font-bold leading-tight tracking-tight text-slate-900 sm:text-[30px]">Profil Saya</h1>
        <p class="mt-1 text-sm text-slate-500">Informasi akun dan pengaturan keamanan Anda.</p>
    </div>

    @if (session('warning'))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-sm font-medium text-amber-800"
            role="alert">
            {{ session('warning') }}
        </div>
    @endif

    @if ($user->must_change_password)
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-sm text-amber-800"
            role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 3.94 2.7 17.06A1.9 1.9 0 0 0 4.34 20h15.32a1.9 1.9 0 0 0 1.64-2.94L13.66 3.94a1.9 1.9 0 0 0-3.32 0Z" />
            </svg>
            <p class="leading-relaxed">
                Anda harus mengganti kata sandi sementara terlebih dahulu. Buka tab Keamanan untuk membuat kata sandi baru.
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">

        {{-- Kartu identitas --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
            <x-user-avatar :name="$user->name" class="mx-auto h-20 w-20 text-xl" />

            <h2 class="mt-4 text-lg font-bold text-slate-800">{{ $user->name }}</h2>
            <p class="text-sm text-slate-500">{{ $user->position ?? 'Pemohon' }}</p>

            <div class="mt-3 flex flex-wrap justify-center gap-1.5">
                @foreach ($roles as $role)
                    <span
                        class="rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-semibold text-blue-700">{{ $role }}</span>
                @endforeach
            </div>

            <dl class="mt-6 space-y-3 border-t border-slate-100 pt-5 text-left">
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Email</dt>
                    <dd class="truncate text-sm font-medium text-slate-700">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">NIP</dt>
                    <dd class="text-sm font-medium text-slate-700">{{ $user->nip ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">OPD</dt>
                    <dd class="text-sm font-medium text-slate-700">{{ $user->opd->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Status Akun</dt>
                    <dd class="mt-1">
                        @if ($user->is_active)
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Aktif
                            </span>
                        @else
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-semibold text-rose-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Tidak Aktif
                            </span>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        {{-- Form --}}
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex gap-1 border-b border-slate-100 px-5 pt-4">
                <button type="button" x-on:click="tab = 'profil'"
                    class="border-b-2 px-4 pb-3 text-sm font-semibold transition"
                    :class="tab === 'profil' ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'">
                    Data Pribadi
                </button>
                <button type="button" x-on:click="tab = 'keamanan'"
                    class="border-b-2 px-4 pb-3 text-sm font-semibold transition"
                    :class="tab === 'keamanan' ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'">
                    Keamanan
                </button>
            </div>

            {{-- Data pribadi --}}
            <div x-show="tab === 'profil'" class="p-6">
                <form wire:submit="updateProfile" class="space-y-5">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">Nama Lengkap</label>
                            <input type="text" value="{{ $user->name }}" disabled
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">Alamat Email</label>
                            <input type="email" value="{{ $user->email }}" disabled
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">NIP</label>
                            <input type="text" value="{{ $user->nip ?? '-' }}" disabled
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">Jabatan</label>
                            <input type="text" value="{{ $user->position ?? '-' }}" disabled
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-500">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="phone_number" class="mb-1.5 block text-sm font-semibold text-slate-700">Nomor
                                Telepon</label>
                            <input id="phone_number" type="text" wire:model="phone_number"
                                placeholder="Contoh: 081234567890"
                                class="w-full rounded-xl border px-3.5 py-2.5 text-sm text-slate-800 focus:border-blue-400 focus:outline-none focus:ring-4 focus:ring-blue-100 {{ $errors->has('phone_number') ? 'border-rose-400' : 'border-slate-200' }}">
                            @error('phone_number')
                                <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-3 border-t border-slate-100 pt-5">
                        <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            Simpan Perubahan
                        </button>

                        <span x-data="{ show: false }" x-init="$wire.on('profile-updated', () => { show = true; setTimeout(() => show = false, 3000) })"
                            x-show="show" x-cloak
                            class="text-xs font-semibold text-emerald-600">Profil berhasil diperbarui.</span>
                    </div>
                </form>
            </div>

            {{-- Keamanan --}}
            <div x-show="tab === 'keamanan'" class="p-6">
                <form wire:submit="updatePassword" class="space-y-5">
                    <div>
                        <label for="current_password" class="mb-1.5 block text-sm font-semibold text-slate-700">Kata
                            Sandi Saat Ini</label>
                        <input id="current_password" type="password" wire:model="current_password"
                            class="w-full rounded-xl border px-3.5 py-2.5 text-sm text-slate-800 focus:border-blue-400 focus:outline-none focus:ring-4 focus:ring-blue-100 {{ $errors->has('current_password') ? 'border-rose-400' : 'border-slate-200' }}">
                        @error('current_password')
                            <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Kata Sandi
                                Baru</label>
                            <input id="password" type="password" wire:model="password"
                                class="w-full rounded-xl border px-3.5 py-2.5 text-sm text-slate-800 focus:border-blue-400 focus:outline-none focus:ring-4 focus:ring-blue-100 {{ $errors->has('password') ? 'border-rose-400' : 'border-slate-200' }}">
                            @error('password')
                                <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label for="password_confirmation"
                                class="mb-1.5 block text-sm font-semibold text-slate-700">Konfirmasi Kata Sandi</label>
                            <input id="password_confirmation" type="password" wire:model="password_confirmation"
                                class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-800 focus:border-blue-400 focus:outline-none focus:ring-4 focus:ring-blue-100">
                        </div>
                    </div>

                    <div class="flex items-center gap-3 border-t border-slate-100 pt-5">
                        <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                            Perbarui Kata Sandi
                        </button>

                        <span x-data="{ show: false }" x-init="$wire.on('password-updated', () => { show = true; setTimeout(() => show = false, 3000) })"
                            x-show="show" x-cloak
                            class="text-xs font-semibold text-emerald-600">Kata sandi berhasil diubah.</span>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
