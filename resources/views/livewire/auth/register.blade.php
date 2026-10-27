<div class="w-full">
    <span
        class="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.12em] text-[#1746d1]">
        <span class="h-1.5 w-1.5 rounded-full bg-[#1746d1]" aria-hidden="true"></span>
        Registrasi Pemohon
    </span>

    <h1 class="mt-4 text-[34px] font-bold leading-tight tracking-[-0.03em] text-slate-900 sm:text-[40px]">
        Buat akun Pemohon
    </h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-500 sm:text-base">
        Daftarkan akun pegawai untuk mengajukan dan memantau RFC. Akun baru langsung aktif dengan akses Pemohon.
    </p>

    @if ($opds->isEmpty())
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Belum ada OPD/unit kerja aktif. Registrasi belum dapat diselesaikan; silakan hubungi administrator.
        </div>
    @endif

    <form wire:submit="register" class="mt-7 space-y-5 sm:mt-8">
        <div class="grid gap-5 sm:grid-cols-2">
            {{-- Nama --}}
            <div>
                <label for="name" class="block text-[14px] font-semibold text-slate-900">Nama lengkap</label>
                <input wire:model="name" id="name" type="text" required autofocus autocomplete="name"
                    placeholder="Nama sesuai identitas"
                    class="mt-2 h-[54px] w-full rounded-[10px] border bg-[#f7f9fd] px-4 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('name') ? 'border-red-400' : 'border-slate-200' }}">
                @error('name')
                    <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror
            </div>

            {{-- NIP --}}
            <div>
                <label for="nip" class="block text-[14px] font-semibold text-slate-900">NIP</label>
                <input wire:model="nip" id="nip" type="text" inputmode="numeric" required autocomplete="off"
                    placeholder="18 digit NIP" maxlength="18"
                    class="mt-2 h-[54px] w-full rounded-[10px] border bg-[#f7f9fd] px-4 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('nip') ? 'border-red-400' : 'border-slate-200' }}">
                @error('nip')
                    <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror
            </div>
        </div>

        {{-- OPD --}}
        <div>
            <label for="opd_id" class="block text-[14px] font-semibold text-slate-900">OPD/unit kerja</label>
            <select wire:model="opd_id" id="opd_id" required @disabled($opds->isEmpty())
                class="mt-2 h-[54px] w-full rounded-[10px] border bg-[#f7f9fd] px-4 text-[14px] text-slate-900 outline-none transition focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:text-slate-400 {{ $errors->has('opd_id') ? 'border-red-400' : 'border-slate-200' }}">
                <option value="">Pilih OPD/unit kerja</option>
                @foreach ($opds as $opd)
                    <option value="{{ $opd->id }}">{{ $opd->name }}</option>
                @endforeach
            </select>
            @error('opd_id')
                <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
            @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            {{-- Jabatan --}}
            <div>
                <label for="position" class="block text-[14px] font-semibold text-slate-900">Jabatan</label>
                <input wire:model="position" id="position" type="text" required autocomplete="organization-title"
                    placeholder="Jabatan di unit kerja"
                    class="mt-2 h-[54px] w-full rounded-[10px] border bg-[#f7f9fd] px-4 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('position') ? 'border-red-400' : 'border-slate-200' }}">
                @error('position')
                    <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror
            </div>

            {{-- Nomor telepon --}}
            <div>
                <label for="phone_number" class="block text-[14px] font-semibold text-slate-900">
                    Nomor telepon <span class="font-normal text-slate-400">(opsional)</span>
                </label>
                <input wire:model="phone_number" id="phone_number" type="tel" autocomplete="tel"
                    placeholder="08xxxxxxxxxx"
                    class="mt-2 h-[54px] w-full rounded-[10px] border bg-[#f7f9fd] px-4 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('phone_number') ? 'border-red-400' : 'border-slate-200' }}">
                @error('phone_number')
                    <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror
            </div>
        </div>

        {{-- Email --}}
        <div>
            <label for="email" class="block text-[14px] font-semibold text-slate-900">Alamat email</label>
            <input wire:model="email" id="email" type="email" required autocomplete="email"
                placeholder="nama@instansi.go.id"
                class="mt-2 h-[54px] w-full rounded-[10px] border bg-[#f7f9fd] px-4 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('email') ? 'border-red-400' : 'border-slate-200' }}">
            @error('email')
                <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
            @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2" x-data="{ showPassword: false, showConfirmation: false }">
            {{-- Password --}}
            <div>
                <label for="password" class="block text-[14px] font-semibold text-slate-900">Kata sandi</label>
                <div class="relative mt-2">
                    <input wire:model="password" id="password" :type="showPassword ? 'text' : 'password'" required
                        autocomplete="new-password" placeholder="Minimal 8 karakter"
                        class="h-[54px] w-full rounded-[10px] border bg-[#f7f9fd] pl-4 pr-24 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('password') ? 'border-red-400' : 'border-slate-200' }}">
                    <button type="button" x-on:click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-3 text-[12px] font-semibold text-[#1746d1] transition hover:text-[#123ab0] focus:outline-none focus-visible:underline"
                        x-text="showPassword ? 'Sembunyikan' : 'Tampilkan'">Tampilkan</button>
                </div>
                @error('password')
                    <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror
            </div>

            {{-- Konfirmasi password --}}
            <div>
                <label for="password_confirmation" class="block text-[14px] font-semibold text-slate-900">
                    Konfirmasi kata sandi
                </label>
                <div class="relative mt-2">
                    <input wire:model="password_confirmation" id="password_confirmation"
                        :type="showConfirmation ? 'text' : 'password'" required autocomplete="new-password"
                        placeholder="Ulangi kata sandi"
                        class="h-[54px] w-full rounded-[10px] border bg-[#f7f9fd] pl-4 pr-24 text-[14px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('password_confirmation') ? 'border-red-400' : 'border-slate-200' }}">
                    <button type="button" x-on:click="showConfirmation = !showConfirmation"
                        class="absolute inset-y-0 right-3 text-[12px] font-semibold text-[#1746d1] transition hover:text-[#123ab0] focus:outline-none focus-visible:underline"
                        x-text="showConfirmation ? 'Sembunyikan' : 'Tampilkan'">Tampilkan</button>
                </div>
                @error('password_confirmation')
                    <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="register" @disabled($opds->isEmpty())
            class="flex h-[56px] w-full items-center justify-center gap-2 rounded-[10px] bg-[#1746d1] px-6 text-[16px] font-semibold text-white shadow-sm transition duration-150 hover:bg-[#123ab0] focus:outline-none focus:ring-4 focus:ring-blue-200 disabled:cursor-not-allowed disabled:opacity-60">
            <svg wire:loading wire:target="register" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"
                aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
            </svg>
            <span wire:loading.remove wire:target="register">Daftar sebagai Pemohon</span>
            <span wire:loading wire:target="register">Memproses...</span>
        </button>
    </form>

    <p class="mt-6 text-sm text-slate-500">
        Sudah punya akun?
        <a href="{{ route('login') }}" wire:navigate
            class="font-semibold text-[#1746d1] transition hover:text-[#123ab0] hover:underline">Masuk di sini</a>
    </p>
</div>
