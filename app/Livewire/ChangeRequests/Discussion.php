<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\RfcStatus;
use App\Exceptions\RfcCommentException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcCommentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Discussion extends Component
{
    public const PAGE_SIZE = 30;

    public ChangeRequest $rfc;

    public string $body = '';

    public bool $internal = false;

    public int $limit = self::PAGE_SIZE;

    public string $statusMessage = '';

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('view', $rfc);
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    public function submit(): void
    {
        $this->authorize('comment', $this->rfc);
        $this->resetValidation('body');
        $this->statusMessage = '';

        $this->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'body.required' => 'Komentar wajib diisi.',
            'body.string' => 'Komentar harus berupa teks.',
            'body.min' => 'Komentar minimal 2 karakter.',
            'body.max' => 'Komentar maksimal 1000 karakter.',
        ]);

        $internal = $this->internal && $this->canWriteInternal();

        try {
            app(RfcCommentService::class)->add($this->rfc, $this->currentUser(), $this->body, $internal);
        } catch (RfcCommentException $exception) {
            $this->addError('body', $exception->getMessage());

            return;
        }

        $this->body = '';
        $this->internal = false;
        $this->statusMessage = 'Komentar berhasil dikirim.';
    }

    public function canWrite(): bool
    {
        return Gate::allows('comment', $this->rfc);
    }

    public function canWriteInternal(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $this->canWrite()) {
            return false;
        }

        if ($this->rfc->submitted_by_id === $user->id) {
            return false;
        }

        return $user->hasRole('Change Manager')
            || ($user->isReleaseTeam() && $this->rfc->hasReleaseTaskFor($user));
    }

    public function writeDeniedReason(): string
    {
        if ($this->rfc->trashed()
            || in_array(RfcStatus::fromValue($this->rfc->status), [RfcStatus::CLOSED, RfcStatus::REJECTED], true)) {
            return 'Diskusi ditutup karena RFC sudah selesai/ditolak.';
        }

        if (RfcStatus::fromValue($this->rfc->status) === RfcStatus::DRAFT) {
            return 'Diskusi tersedia setelah RFC diajukan.';
        }

        return 'Hanya Pemohon, Change Manager, dan Tim Rilis yang ditugaskan yang dapat menulis komentar.';
    }

    public function render(): View
    {
        $this->authorize('view', $this->rfc);

        $user = $this->currentUser();
        $showInternal = Gate::forUser($user)->allows('viewInternalComments', $this->rfc);

        $comments = $this->rfc->comments()
            ->with(['user:id,name', 'user.roles:id,name'])
            ->when(! $showInternal, fn ($query) => $query->where('is_internal', false))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->limit + 1)
            ->get(['id', 'change_request_id', 'user_id', 'comment', 'is_internal', 'created_at']);

        $hasMore = $comments->count() > $this->limit;
        $comments = $comments->take($this->limit)->reverse()->values();

        return view('livewire.change-requests.discussion', [
            'comments' => $comments,
            'hasMore' => $hasMore,
        ]);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
