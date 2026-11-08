<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-[26px] font-bold leading-tight tracking-tight text-slate-900 sm:text-[30px]">Kelola Pengguna</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                Atur akses akun, reset kata sandi sementara, dan aktifkan atau nonaktifkan pengguna.
            </p>
        </div>

        <span class="inline-flex items-center gap-2 self-start rounded-full bg-blue-50 px-3.5 py-2 text-xs font-semibold text-blue-700 sm:self-auto">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
            </svg>
            Manajemen pengguna
        </span>
    </div>

    @if ($statusMessage)
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-800"
            role="status">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <p class="flex-1 font-medium">{{ $statusMessage }}</p>
            <button type="button" wire:click="$set('statusMessage', '')" class="text-emerald-500 hover:text-emerald-700"
                aria-label="Tutup pesan">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    @error('toggleActive')
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-800" role="alert">
            {{ $message }}
        </div>
    @enderror

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-sm">
                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                    </svg>
                </span>
                <input type="search" wire:model.live.debounce.300ms="search"
                    placeholder="Cari nama, email, atau NIP..."
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100">
            </div>

            <p class="text-xs text-slate-500">
                <span class="font-semibold text-slate-700">{{ $users->total() }}</span> pengguna
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-semibold">Nama</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Email</th>
                        <th scope="col" class="px-5 py-3 font-semibold">NIP</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Role</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr wire:key="managed-user-{{ $user->id }}" class="align-top">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-800">{{ $user->name }}</p>
                                @if ($user->must_change_password)
                                    <span
                                        class="mt-1.5 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                                        Wajib ganti password
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $user->email }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $user->nip ?: '-' }}</td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->roles as $role)
                                        <span
                                            class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700">
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-400">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @if ($user->is_active)
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                        Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @can('resetPassword', $user)
                                        <button type="button" wire:click="openResetModal({{ $user->id }})"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100">
                                            Reset Password
                                        </button>
                                    @endcan

                                    @can('toggleActive', $user)
                                        <button type="button" wire:click="toggleActive({{ $user->id }})"
                                            wire:loading.attr="disabled" wire:target="toggleActive"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 {{ $user->is_active ? 'border-rose-200 bg-rose-50 text-rose-700 hover:border-rose-300 hover:bg-rose-100' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100' }}">
                                            {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    @endcan

                                    @if ($user->id === auth()->id() || ($user->hasRole('Super Admin') && ! auth()->user()?->hasRole('Super Admin')))
                                        <span class="self-center text-xs font-medium text-slate-400">Tidak tersedia</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-16 text-center">
                                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                            d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                    </svg>
                                </span>
                                <p class="mt-3 text-sm font-semibold text-slate-700">Tidak ada pengguna yang cocok</p>
                                <p class="mt-1 text-xs text-slate-500">Ubah kata kunci pencarian.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    @if ($showResetModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6" role="dialog" aria-modal="true"
            aria-labelledby="reset-password-title">
            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" wire:click="closeResetModal"></div>

            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 id="reset-password-title" class="text-lg font-bold text-slate-900">
                            {{ $passwordGenerated ? 'Kata Sandi Sementara' : 'Reset Kata Sandi' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            @if ($passwordGenerated)
                                Kata sandi sementara berhasil dibuat untuk {{ $selectedUser?->name ?? 'pengguna' }}.
                            @else
                                Kata sandi lama akan diganti dan pengguna wajib menggantinya saat login.
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="closeResetModal"
                        class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Tutup">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-5 px-6 py-5">
                    @if ($passwordGenerated)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Catat sekarang, tidak akan ditampilkan lagi.
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Kata sandi sementara</p>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                                <code class="break-all text-lg font-bold tracking-wide text-slate-900">{{ $temporaryPassword }}</code>
                                <button type="button" x-data="{ copied: false }"
                                    x-on:click="navigator.clipboard.writeText(@js($temporaryPassword)); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    <span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-slate-100 pt-5">
                            <button type="button" wire:click="closeResetModal"
                                class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                                Selesai
                            </button>
                        </div>
                    @else
                        @if ($selectedUser)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Pengguna</p>
                                <p class="mt-1 font-semibold text-slate-800">{{ $selectedUser->name }}</p>
                                <p class="text-xs text-slate-500">{{ $selectedUser->email }}</p>
                            </div>
                        @endif

                        @error('resetPassword')
                            <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror

                        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                            <button type="button" wire:click="closeResetModal"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                Batal
                            </button>
                            <button type="button" wire:click="resetPassword" wire:loading.attr="disabled"
                                wire:target="resetPassword"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                                <span wire:loading.remove wire:target="resetPassword">Reset Password</span>
                                <span wire:loading wire:target="resetPassword">Memproses...</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
