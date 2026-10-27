<div class="w-full">
    <h1 class="text-[38px] font-bold leading-tight tracking-[-0.03em] text-slate-900 sm:text-[44px] xl:text-[46px]">
        Selamat datang
    </h1>
    <p class="mt-2 text-base text-slate-500 sm:text-lg">
        Masuk dengan akun pegawai yang sudah terdaftar.
    </p>

    <form wire:submit="login" class="mt-9 space-y-7 sm:mt-10">
        {{-- Email --}}
        <div>
            <label for="email" class="block text-[15px] font-semibold text-slate-900">Alamat email</label>
            <input wire:model="email" id="email" type="email" required autofocus autocomplete="username"
                class="mt-3 h-[60px] w-full rounded-[10px] border bg-[#f7f9fd] px-4 text-[15px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('email') ? 'border-red-400' : 'border-slate-200' }}">
            @error('email')
                <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
            @enderror
        </div>

        {{-- Password --}}
        <div x-data="{ show: false }">
            <label for="password" class="block text-[15px] font-semibold text-slate-900">Kata sandi</label>
            <div class="relative mt-3">
                <input wire:model="password" id="password" :type="show ? 'text' : 'password'" required
                    autocomplete="current-password"
                    class="h-[60px] w-full rounded-[10px] border bg-[#f7f9fd] pl-4 pr-28 text-[15px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('password') ? 'border-red-400' : 'border-slate-200' }}">
                <button type="button" x-on:click="show = !show"
                    class="absolute inset-y-0 right-4 text-[15px] font-semibold text-[#1746d1] transition hover:text-[#123ab0] focus:outline-none focus-visible:underline"
                    x-text="show ? 'Sembunyikan' : 'Tampilkan'">Tampilkan</button>
            </div>
            @error('password')
                <span class="mt-2 block text-xs font-medium text-red-600">{{ $message }}</span>
            @enderror
        </div>

        {{-- Ingat saya --}}
        <label class="flex cursor-pointer select-none items-center gap-3 text-[15px] text-slate-600">
            <input wire:model="remember" type="checkbox"
                class="h-[22px] w-[22px] cursor-pointer rounded-[5px] border-slate-300 accent-slate-900 focus:ring-2 focus:ring-[#1746d1] focus:ring-offset-0">
            <span>Ingat saya</span>
        </label>

        {{-- Tombol masuk --}}
        <button type="submit" wire:loading.attr="disabled" wire:target="login"
            class="flex h-[60px] w-full items-center justify-center gap-2 rounded-[10px] bg-[#1746d1] px-6 text-[17px] font-semibold text-white shadow-sm transition duration-150 hover:bg-[#123ab0] focus:outline-none focus:ring-4 focus:ring-blue-200 disabled:cursor-wait disabled:opacity-80">
            <svg wire:loading wire:target="login" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"
                aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
            </svg>
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Memproses...</span>
        </button>
    </form>

    <p class="mt-7 text-sm text-slate-500">
        Belum punya akun?
        <a href="{{ route('register') }}" wire:navigate
            class="font-semibold text-[#1746d1] transition hover:text-[#123ab0] hover:underline">Daftar sebagai
            Pemohon</a>
    </p>
</div>
