<?php

namespace App\Livewire\Admin;

use App\Exceptions\ReleaseTeamAccountException;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\ReleaseTeamAccountLinker;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ReleaseTeamAccounts extends Component
{
    public bool $showLinkModal = false;

    public ?int $selectedMemberId = null;

    public string $selectedUserId = '';

    public string $search = '';

    public string $statusMessage = '';

    /**
     * Periksa otorisasi pada setiap request Livewire, termasuk request lanjutan.
     */
    public function boot(): void
    {
        Gate::authorize('viewAny', ReleaseTeamMember::class);
    }

    public function openLinkModal(int $memberId): void
    {
        $member = ReleaseTeamMember::query()->findOrFail($memberId);

        Gate::authorize('link', $member);

        $this->selectedMemberId = $member->id;
        $this->selectedUserId = '';
        $this->resetValidation();
        $this->showLinkModal = true;
    }

    public function closeLinkModal(): void
    {
        $this->showLinkModal = false;
        $this->selectedMemberId = null;
        $this->selectedUserId = '';
        $this->resetValidation();
    }

    public function link(ReleaseTeamAccountLinker $linker): void
    {
        $this->validate([
            'selectedMemberId' => ['required', 'integer', 'exists:release_team_members,id'],
            'selectedUserId' => ['required', 'integer', 'exists:users,id'],
        ], [
            'selectedMemberId.required' => 'Pilih anggota Tim Rilis yang akan ditautkan.',
            'selectedUserId.required' => 'Pilih akun yang akan ditautkan.',
        ]);

        $member = ReleaseTeamMember::query()->findOrFail($this->selectedMemberId);

        Gate::authorize('link', $member);

        /** @var User $actor */
        $actor = Auth::user();

        /** @var User $targetUser */
        $targetUser = User::query()->findOrFail((int) $this->selectedUserId);

        try {
            $linkedMember = $linker->link($member, $targetUser, $actor);
        } catch (ReleaseTeamAccountException $exception) {
            $this->addError('selectedUserId', $exception->getMessage());

            return;
        }

        $this->closeLinkModal();

        $this->statusMessage = "Akun {$linkedMember->user->name} berhasil ditautkan ke anggota Tim Rilis {$linkedMember->name}.";
    }

    public function render(): View
    {
        $members = ReleaseTeamMember::query()
            ->with('user')
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $search): void {
                    $search->where('name', 'like', "%{$this->search}%")
                        ->orWhere('nip_or_nik', 'like', "%{$this->search}%")
                        ->orWhere('role_title', 'like', "%{$this->search}%");
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $selectedMember = $this->selectedMemberId === null
            ? null
            : ReleaseTeamMember::query()->with('user')->find($this->selectedMemberId);

        return view('livewire.admin.release-team-accounts', [
            'members' => $members,
            'selectedMember' => $selectedMember,
            'availableUsers' => $this->availableUsers($selectedMember),
        ])->title('Akun Tim Rilis');
    }

    /**
     * Akun yang memenuhi syarat ditautkan. Pemeriksaan final tetap dilakukan
     * ulang oleh service agar request yang dimanipulasi tidak dapat melewati
     * aturan role, status aktif, maupun tautan ganda.
     *
     * @return Collection<int, User>
     */
    private function availableUsers(?ReleaseTeamMember $selectedMember): Collection
    {
        if (! $selectedMember
            || ! $selectedMember->is_active
            || $selectedMember->user_id !== null) {
            return collect();
        }

        /** @var User $actor */
        $actor = Auth::user();

        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($actor->id)
            ->whereDoesntHave('roles', function (Builder $query): void {
                $query->whereIn('name', ['Admin', 'Change Manager', 'Super Admin']);
            })
            ->whereDoesntHave('releaseTeamMember')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
