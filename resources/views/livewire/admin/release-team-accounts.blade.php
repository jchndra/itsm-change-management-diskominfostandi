<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-[26px] font-bold leading-tight tracking-tight text-slate-900 sm:text-[30px]">Akun Tim Rilis</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                Tautkan akun pengguna yang sudah ada ke anggota Tim Rilis. Halaman ini tidak membuat akun baru.
            </p>
        </div>

        <span class="inline-flex items-center gap-2 self-start rounded-full bg-blue-50 px-3.5 py-2 text-xs font-semibold text-blue-700 sm:self-auto">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
            </svg>
            Manajemen akses
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

    <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-sm text-amber-800">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 3.94 2.7 17.06A1.9 1.9 0 0 0 4.34 20h15.32a1.9 1.9 0 0 0 1.64-2.94L13.66 3.94a1.9 1.9 0 0 0-3.32 0Z" />
        </svg>
        <p class="leading-relaxed">
            Hanya akun aktif yang belum tertaut dan bukan Admin, Change Manager, atau Super Admin yang dapat dipilih.
            Penautan akan memberikan role <span class="font-semibold">Tim Rilis</span> dan dicatat pada activity log.
        </p>
    </div>

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
                    placeholder="Cari nama, NIP/NIK, atau peran..."
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100">
            </div>

            <p class="text-xs text-slate-500">
                <span class="font-semibold text-slate-700">{{ $members->count() }}</span> anggota Tim Rilis
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-semibold">Anggota</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Peran</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Akun Tertaut</th>
                        <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($members as $member)
                        <tr wire:key="release-team-member-{{ $member->id }}" class="align-top">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-800">{{ $member->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    NIP/NIK: {{ $member->nip_or_nik ?: '-' }}
                                </p>
                                <p class="text-xs text-slate-500">{{ $member->phone_number ?: 'Nomor telepon belum diisi' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $member->role_title }}</td>
                            <td class="px-5 py-4">
                                @if ($member->user)
                                    <p class="font-medium text-slate-800">{{ $member->user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $member->user->email }}</p>
                                    <span class="mt-1.5 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                        Sudah tertaut
                                    </span>
                                @else
                                    <p class="text-sm font-medium text-slate-500">Belum tertaut</p>
                                    <p class="mt-0.5 text-xs text-slate-400">Pilih akun pengguna yang sudah ada.</p>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if ($member->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                        Tidak aktif
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if (! $member->is_active)
                                    <span class="text-xs font-medium text-slate-400">Tidak dapat ditautkan</span>
                                @elseif ($member->user_id !== null)
                                    <span class="text-xs font-medium text-slate-400">Sudah tertaut</span>
                                @else
                                    <button type="button" wire:click="openLinkModal({{ $member->id }})"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                        </svg>
                                        Tautkan Akun
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-16 text-center">
                                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                            d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                    </svg>
                                </span>
                                <p class="mt-3 text-sm font-semibold text-slate-700">Tidak ada anggota yang cocok</p>
                                <p class="mt-1 text-xs text-slate-500">Ubah kata kunci pencarian atau tambahkan data anggota Tim Rilis.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showLinkModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6" role="dialog" aria-modal="true"
            aria-labelledby="link-account-title">
            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" wire:click="closeLinkModal"></div>

            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 id="link-account-title" class="text-lg font-bold text-slate-900">Tautkan Akun Tim Rilis</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Akun akan memperoleh akses ke RFC yang ditugaskan kepadanya.
                        </p>
                    </div>
                    <button type="button" wire:click="closeLinkModal"
                        class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Tutup">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="link" class="space-y-5 px-6 py-5">
                    @if ($selectedMember)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Anggota Tim Rilis</p>
                            <p class="mt-1 font-semibold text-slate-800">{{ $selectedMember->name }}</p>
                            <p class="text-xs text-slate-500">{{ $selectedMember->role_title }} &middot; NIP/NIK:
                                {{ $selectedMember->nip_or_nik ?: '-' }}</p>
                        </div>
                    @endif

                    @if ($availableUsers->isEmpty())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Tidak ada akun aktif yang memenuhi syarat. Akun Admin, Change Manager, Super Admin, akun
                            yang sudah tertaut, dan akun Anda sendiri tidak dapat dipilih.
                        </div>
                    @else
                        <div>
                            <label for="selectedUserId" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Pilih Akun
                            </label>
                            <select id="selectedUserId" wire:model="selectedUserId"
                                class="w-full rounded-xl border px-3.5 py-2.5 text-sm text-slate-800 focus:border-blue-400 focus:outline-none focus:ring-4 focus:ring-blue-100 {{ $errors->has('selectedUserId') ? 'border-rose-400' : 'border-slate-200' }}">
                                <option value="">— Pilih akun pengguna —</option>
                                @foreach ($availableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>
                                @endforeach
                            </select>
                            @error('selectedUserId')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    @error('selectedMemberId')
                        <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="closeLinkModal"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                            Batal
                        </button>
                        <button type="submit" @disabled($availableUsers->isEmpty())
                            wire:loading.attr="disabled" wire:target="link"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading.remove wire:target="link">Tautkan Akun</span>
                            <span wire:loading wire:target="link">Memproses...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
