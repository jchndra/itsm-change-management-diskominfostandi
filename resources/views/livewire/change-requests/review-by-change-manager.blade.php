@php
    $approvedValue = \App\Enums\DecisionStatus::APPROVED->value;
    $revisionValue = \App\Enums\DecisionStatus::REVISION_REQUIRED->value;
    $rejectedValue = \App\Enums\DecisionStatus::REJECTED->value;
    $selectedMember = $eligibleMembers->firstWhere('id', $selectedMemberId);
    $evaluationFilled = filled($risk_level) && filled($impact_level) && filled($urgency_level) && filled($classification);
    $decisionBadge = match ($decisionStatus) {
        $approvedValue => ['label' => 'Setujui', 'class' => 'bg-emerald-100 text-emerald-700'],
        $revisionValue => ['label' => 'Perlu Revisi', 'class' => 'bg-amber-100 text-amber-700'],
        $rejectedValue => ['label' => 'Tolak', 'class' => 'bg-rose-100 text-rose-700'],
        default => ['label' => 'Belum dipilih', 'class' => 'bg-slate-100 text-slate-600'],
    };
    $confirmMessage = match ($decisionStatus) {
        $approvedValue => 'Setujui dan kirim disposisi ke '.($selectedMember?->name ?? 'Tim Rilis').'?',
        $rejectedValue => 'Tolak RFC ini? Tindakan tidak dapat dibatalkan.',
        default => 'Kembalikan RFC ini kepada Pemohon untuk direvisi?',
    };
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('change-requests.show', $rfc->id) }}"
                    class="text-slate-400 transition hover:text-slate-600" wire:navigate>
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-slate-900">Tinjauan Change Manager</h1>
            </div>
            <p class="mt-1 pl-9 text-sm text-slate-500">
                Evaluasi risiko, keputusan, dan disposisi untuk {{ $rfc->displayIdentifier() }}
            </p>
        </div>
        <a href="{{ route('change-requests.show', $rfc->id) }}"
            class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
            wire:navigate>
            Batal
        </a>
    </div>

    @include('livewire.change-requests.partials.rfc-summary', ['rfc' => $rfc])

    @error('status')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
            {{ $message }}
        </div>
    @enderror

    @error('disposition')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit.prevent="submit" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Langkah 1: Evaluasi Risiko --}}
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">1</span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Evaluasi Risiko</h2>
                        <p class="text-xs text-slate-500">Tentukan tingkat risiko, dampak, urgensi, dan klasifikasi perubahan.</p>
                    </div>
                </div>

                @if ($rfc->emergency_unconfirmed)
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800">
                        Pemohon mengusulkan kategori Emergency. Pertahankan Emergency Change bila memenuhi syarat, atau pilih klasifikasi lain.
                    </div>
                @endif

                <div class="mt-4 grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="space-y-2">
                        <label for="risk_level" class="block text-sm font-semibold text-slate-700">Tingkat Risiko *</label>
                        <select id="risk_level" wire:model.live="risk_level"
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 {{ $errors->has('risk_level') ? 'border-red-500' : 'border-slate-300' }}">
                            <option value="Rendah">Rendah (Dampak minimal pada sistem)</option>
                            <option value="Sedang">Sedang (Dampak terbatas pada fungsi tertentu)</option>
                            <option value="Tinggi">Tinggi (Berpotensi mengganggu layanan utama)</option>
                            <option value="Kritis">Kritis (Risiko tinggi penghentian layanan publik)</option>
                        </select>
                        <x-risk-badge :risiko="$risk_level" />
                        @error('risk_level')
                            <span class="block text-xs text-rose-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="impact_level" class="block text-sm font-semibold text-slate-700">Tingkat Dampak *</label>
                        <select id="impact_level" wire:model.live="impact_level"
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 {{ $errors->has('impact_level') ? 'border-red-500' : 'border-slate-300' }}">
                            <option value="Rendah">Rendah (Internal OPD)</option>
                            <option value="Sedang">Sedang (Beberapa Unit Kerja / OPD)</option>
                            <option value="Tinggi">Tinggi (Seluruh Pemerintah Kota Bekasi)</option>
                            <option value="Kritis">Kritis (Layanan Publik Masyarakat)</option>
                        </select>
                        <x-risk-badge :risiko="$impact_level" />
                        @error('impact_level')
                            <span class="block text-xs text-rose-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="urgency_level" class="block text-sm font-semibold text-slate-700">Urgensi Perubahan *</label>
                        <select id="urgency_level" wire:model.live="urgency_level"
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 {{ $errors->has('urgency_level') ? 'border-red-500' : 'border-slate-300' }}">
                            <option value="Rendah">Rendah (Dapat dijadwalkan rutin)</option>
                            <option value="Normal">Normal (Siklus rilis standar)</option>
                            <option value="Tinggi">Tinggi (Membutuhkan penanganan segera)</option>
                            <option value="Emergency">Emergency (Penanganan darurat langsung)</option>
                        </select>
                        <x-risk-badge :risiko="$urgency_level" />
                        @error('urgency_level')
                            <span class="block text-xs text-rose-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="classification" class="block text-sm font-semibold text-slate-700">Klasifikasi Perubahan *</label>
                        <select id="classification" wire:model.live="classification"
                            class="w-full rounded-lg border px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 {{ $errors->has('classification') ? 'border-red-500' : 'border-slate-300' }}">
                            <option value="Standard Change">Standard Change (Rutin &amp; Ter-pre-otorisasi)</option>
                            <option value="Normal Change">Normal Change (Melalui review Change Manager)</option>
                            <option value="Emergency Change">Emergency Change (Penanganan segera)</option>
                        </select>
                        @error('classification')
                            <span class="block text-xs text-rose-600">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="mt-5 space-y-2" x-data="{ count: {{ mb_strlen($analysis_notes) }} }">
                    <label for="analysis_notes" class="block text-sm font-semibold text-slate-700">Catatan Analisis Risiko &amp; Rencana Mitigasi *</label>
                    <textarea id="analysis_notes" wire:model="analysis_notes" rows="4" maxlength="1000"
                        x-on:input="count = $event.target.value.length"
                        placeholder="Tuliskan potensi kegagalan, prosedur pengujian, serta rencana pengembalian (rollback plan)..."
                        class="w-full rounded-lg border px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 {{ $errors->has('analysis_notes') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
                    <div class="flex items-center justify-between gap-3">
                        @error('analysis_notes')
                            <span class="text-xs text-rose-600">{{ $message }}</span>
                        @else
                            <span class="text-xs text-slate-400">Jelaskan potensi kegagalan, pengujian, dan rollback.</span>
                        @enderror
                        <span class="shrink-0 text-[11px] text-slate-400"><span x-text="count">{{ mb_strlen($analysis_notes) }}</span>/1000</span>
                    </div>
                </div>
            </section>

            {{-- Langkah 2: Keputusan --}}
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">2</span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Keputusan</h2>
                        <p class="text-xs text-slate-500">Pilih satu keputusan resmi Change Manager untuk RFC ini.</p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <label class="block cursor-pointer">
                        <input type="radio" wire:model.live="decisionStatus" value="{{ $approvedValue }}" class="peer sr-only">
                        <div class="flex h-full items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 transition hover:border-emerald-300 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:ring-2 peer-checked:ring-emerald-200">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">Setujui</span>
                                <span class="mt-0.5 block text-xs text-slate-500">RFC disetujui dan langsung didisposisikan ke Tim Rilis.</span>
                            </span>
                        </div>
                    </label>

                    <label class="block cursor-pointer">
                        <input type="radio" wire:model.live="decisionStatus" value="{{ $revisionValue }}" class="peer sr-only">
                        <div class="flex h-full items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 transition hover:border-amber-300 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:ring-2 peer-checked:ring-amber-200">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">Perlu Revisi</span>
                                <span class="mt-0.5 block text-xs text-slate-500">RFC dikembalikan kepada Pemohon untuk diperbaiki.</span>
                            </span>
                        </div>
                    </label>

                    <label class="block cursor-pointer">
                        <input type="radio" wire:model.live="decisionStatus" value="{{ $rejectedValue }}" class="peer sr-only">
                        <div class="flex h-full items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 transition hover:border-rose-300 peer-checked:border-rose-500 peer-checked:bg-rose-50 peer-checked:ring-2 peer-checked:ring-rose-200">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">Tolak</span>
                                <span class="mt-0.5 block text-xs text-slate-500">RFC ditolak dan tidak dapat dilanjutkan.</span>
                            </span>
                        </div>
                    </label>
                </div>

                @error('decisionStatus')
                    <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span>
                @enderror

                <div class="mt-5">
                    <label for="notes" class="mb-1 block text-sm font-semibold text-slate-700">
                        @if ($decisionStatus === $revisionValue)
                            Catatan Revisi *
                        @elseif ($decisionStatus === $rejectedValue)
                            Alasan Penolakan *
                        @else
                            Catatan Keputusan &amp; Pertimbangan *
                        @endif
                    </label>
                    <textarea id="notes" wire:model="notes" rows="4" maxlength="1000"
                        placeholder="{{ $decisionStatus === $revisionValue ? 'Tuliskan bagian yang harus diperbaiki Pemohon...' : ($decisionStatus === $rejectedValue ? 'Tuliskan alasan penolakan RFC...' : 'Masukkan ringkasan pertimbangan, catatan mitigasi risiko, atau instruksi teknis...') }}"
                        class="w-full rounded-lg border px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 {{ $errors->has('notes') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
                    @error('notes')
                        <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                    @enderror
                </div>
            </section>

            {{-- Langkah 3: Disposisi Tim Rilis --}}
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">3</span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Disposisi Tim Rilis</h2>
                        <p class="text-xs text-slate-500">Tentukan satu anggota Tim Rilis yang akan mengeksekusi perubahan.</p>
                    </div>
                </div>

                @if ($decisionStatus === $approvedValue)
                    <div wire:key="disposition-step" class="mt-4 space-y-5">
                        <div class="space-y-2">
                            <span class="block text-sm font-semibold text-slate-700">Penerima Disposisi *</span>
                            @if ($eligibleMembers->isEmpty())
                                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-700">
                                    Belum ada anggota Tim Rilis aktif yang memenuhi syarat.
                                </p>
                            @else
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @foreach ($eligibleMembers as $member)
                                        <label class="block cursor-pointer" wire:key="member-{{ $member->id }}">
                                            <input type="radio" wire:model.live="selectedMemberId" value="{{ $member->id }}" class="peer sr-only">
                                            <div class="rounded-xl border border-slate-200 bg-white p-4 transition hover:border-indigo-300 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:ring-2 peer-checked:ring-indigo-200">
                                                <p class="text-sm font-semibold text-slate-800">{{ $member->name }}</p>
                                                <p class="mt-0.5 text-xs text-slate-500">{{ filled($member->role_title) ? $member->role_title : 'Tim Rilis' }}</p>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            @error('selectedMemberId')
                                <span class="block text-xs text-rose-600">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label for="instruction" class="mb-1 block text-sm font-semibold text-slate-700">Instruksi Disposisi *</label>
                            <textarea id="instruction" wire:model="instruction" rows="4" maxlength="1000"
                                placeholder="Tuliskan instruksi pelaksanaan perubahan untuk anggota Tim Rilis..."
                                class="w-full rounded-lg border px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 {{ $errors->has('instruction') ? 'border-red-500' : 'border-slate-300' }}"></textarea>
                            <p class="mt-1 text-[11px] text-slate-400">Minimal 10 karakter, maksimal 1.000 karakter.</p>
                            @error('instruction')
                                <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                @else
                    <p wire:key="disposition-not-required" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-medium text-slate-500">
                        Disposisi tidak diperlukan untuk keputusan ini.
                    </p>
                @endif
            </section>
        </div>

        {{-- Panel Ringkasan --}}
        <div class="lg:col-span-1">
            <div class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:sticky lg:top-6">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Ringkasan Tinjauan</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Periksa kembali sebelum menyimpan.</p>
                </div>

                <div class="space-y-3 border-t border-slate-100 pt-4 text-xs">
                    <div>
                        <p class="text-slate-400">Tingkat Risiko</p>
                        <div class="mt-1">
                            <x-risk-badge :risiko="$risk_level" />
                        </div>
                    </div>
                    <div>
                        <p class="text-slate-400">Keputusan</p>
                        <span class="mt-1 inline-flex rounded-lg px-2.5 py-1 text-[11px] font-semibold {{ $decisionBadge['class'] }}">
                            {{ $decisionBadge['label'] }}
                        </span>
                    </div>
                    <div>
                        <p class="text-slate-400">Penerima Disposisi</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            {{ $decisionStatus === $approvedValue ? ($selectedMember?->name ?? '-') : '-' }}
                        </p>
                    </div>
                </div>

                <div class="space-y-2 border-t border-slate-100 pt-4 text-xs">
                    <p class="font-semibold text-slate-600">Daftar Periksa</p>

                    <div class="flex items-center gap-2 {{ $evaluationFilled ? 'text-emerald-700' : 'text-slate-400' }}">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Evaluasi risiko terisi</span>
                    </div>

                    <div class="flex items-center gap-2 {{ filled($notes) ? 'text-emerald-700' : 'text-slate-400' }}">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Catatan keputusan terisi</span>
                    </div>

                    <div class="flex items-center gap-2 {{ $selectedMemberId !== null ? 'text-emerald-700' : 'text-slate-400' }}">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Penerima disposisi dipilih</span>
                    </div>
                </div>

                <label class="flex items-start gap-3 rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-xs text-indigo-900">
                    <input type="checkbox" wire:model="confirmed"
                        class="mt-0.5 h-4 w-4 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                    <span>Saya telah memeriksa dokumen dan lampiran serta yakin dengan keputusan ini</span>
                </label>
                @error('confirmed')
                    <span class="block text-xs text-rose-600">{{ $message }}</span>
                @enderror

                <button type="submit" wire:loading.attr="disabled" wire:confirm="{{ $confirmMessage }}"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50">
                    <svg wire:loading.remove class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span wire:loading.remove>Simpan &amp; Selesaikan</span>
                    <span wire:loading>Menyimpan...</span>
                </button>
            </div>
        </div>
    </form>
</div>
