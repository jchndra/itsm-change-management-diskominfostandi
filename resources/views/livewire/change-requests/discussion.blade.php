<div wire:poll.30s.visible aria-label="Diskusi RFC, diperbarui otomatis setiap 30 detik">
    @if (filled($statusMessage))
        <x-ui.alert tone="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif

    @if ($hasMore)
        <div class="mb-4 text-center">
            <x-ui.button type="button" variant="secondary" size="sm" wire:click="loadMore"
                wire:loading.attr="disabled" wire:target="loadMore">
                <span wire:loading.remove wire:target="loadMore">Muat komentar sebelumnya</span>
                <span wire:loading wire:target="loadMore">Memuat...</span>
            </x-ui.button>
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($comments as $comment)
            @php
                $author = $comment->user;
                $authorName = $author?->name ?? 'Pengguna';
                $initials = collect(explode(' ', trim($authorName)))
                    ->filter()
                    ->take(2)
                    ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                    ->implode('');
                $roleLabel = $author?->hasRole('Change Manager')
                    ? 'Change Manager'
                    : ($author?->hasRole('Tim Rilis') ? 'Tim Rilis' : 'Pemohon');
                $avatarTone = match ($roleLabel) {
                    'Change Manager' => 'bg-gradient-to-br from-blue-600 to-sky-500',
                    'Tim Rilis' => 'bg-gradient-to-br from-emerald-600 to-teal-500',
                    default => 'bg-gradient-to-br from-slate-600 to-slate-800',
                };
                $createdAt = $comment->created_at?->copy()->timezone('Asia/Jakarta');
                $absoluteTime = $createdAt?->locale('id')->translatedFormat('d M Y, H:i').' WIB';
            @endphp

            <div wire:key="discussion-comment-{{ $comment->id }}"
                class="flex gap-3 rounded-2xl border border-slate-100 bg-slate-50/50 p-3.5">
                <span
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-bold text-white shadow-sm ring-2 ring-white {{ $avatarTone }}"
                    aria-hidden="true">
                    {{ $initials }}
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-slate-800">{{ $authorName }}</p>
                        <span
                            class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 ring-1 ring-slate-200">
                            {{ $roleLabel }}
                        </span>
                        @if ($comment->is_internal)
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                                Internal
                            </span>
                        @endif
                        @if ($createdAt)
                            <time class="ml-auto text-[11px] text-slate-400"
                                datetime="{{ $createdAt->toIso8601String() }}" title="{{ $absoluteTime }}">
                                {{ $createdAt->diffForHumans() }} &middot; {{ $absoluteTime }}
                            </time>
                        @endif
                    </div>
                    <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
                        {{ $comment->comment }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">Belum ada komentar.</p>
        @endforelse
    </div>

    @if ($this->canWrite())
        <form wire:submit="submit" class="mt-5 space-y-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
            <div>
                <label for="discussion-body" class="block text-xs font-semibold text-slate-600">
                    Komentar
                </label>
                <textarea id="discussion-body" rows="3" maxlength="1000" wire:model.live.debounce.500ms="body"
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-400 focus:outline-none focus:ring-4 focus:ring-blue-100"
                    placeholder="Tulis komentar atau catatan diskusi..."></textarea>
                <p class="mt-1 text-right text-[11px] text-slate-400">
                    {{ mb_strlen($body) }}/1000 karakter
                </p>
                @error('body')
                    <p class="mt-1 text-xs font-medium text-rose-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            @if ($this->canWriteInternal())
                <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                    <input type="checkbox" wire:model="internal"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    Catatan internal (tidak terlihat Pemohon)
                </label>
            @endif

            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Kirim</span>
                <span wire:loading wire:target="submit">Mengirim...</span>
            </x-ui.button>
        </form>
    @else
        <p class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
            {{ $this->writeDeniedReason() }}
        </p>
    @endif
</div>
