<div wire:poll.30s.visible aria-label="Diskusi RFC, diperbarui otomatis setiap 30 detik">
    @if (filled($statusMessage))
        <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800"
            role="status">
            {{ $statusMessage }}
        </p>
    @endif

    @if ($hasMore)
        <div class="mb-4 text-center">
            <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore"
                class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="loadMore">Muat komentar sebelumnya</span>
                <span wire:loading wire:target="loadMore">Memuat...</span>
            </button>
        </div>
    @endif

    <div class="space-y-4">
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
                $createdAt = $comment->created_at?->copy()->timezone('Asia/Jakarta');
                $absoluteTime = $createdAt?->locale('id')->translatedFormat('d M Y, H:i').' WIB';
            @endphp

            <div wire:key="discussion-comment-{{ $comment->id }}"
                class="flex gap-3 rounded-lg border border-slate-100 p-3">
                <span
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700"
                    aria-hidden="true">
                    {{ $initials }}
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-slate-800">{{ $authorName }}</p>
                        <span
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
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
                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ $comment->comment }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">Belum ada komentar.</p>
        @endforelse
    </div>

    @if ($this->canWrite())
        <form wire:submit="submit" class="mt-5 space-y-3 border-t border-slate-100 pt-4">
            <div>
                <label for="discussion-body" class="block text-xs font-semibold text-slate-600">
                    Komentar
                </label>
                <textarea id="discussion-body" rows="3" maxlength="1000" wire:model.live.debounce.500ms="body"
                    class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Catatan internal (tidak terlihat Pemohon)
                </label>
            @endif

            <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                <span wire:loading.remove wire:target="submit">Kirim</span>
                <span wire:loading wire:target="submit">Mengirim...</span>
            </button>
        </form>
    @else
        <p class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
            {{ $this->writeDeniedReason() }}
        </p>
    @endif
</div>
