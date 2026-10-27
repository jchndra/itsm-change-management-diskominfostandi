<?php

namespace App\Livewire\Disposition;

use App\Exceptions\RfcDispositionException;
use App\Models\ChangeRequest;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\RfcDispositionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Assign extends Component
{
    public ChangeRequest $rfc;

    /** @var array<int, int|string> */
    public array $selectedMemberIds = [];

    public string $instruction = '';

    public bool $confirmed = false;

    protected array $rules = [
        'selectedMemberIds' => ['required', 'array', 'min:1'],
        'selectedMemberIds.*' => ['integer', 'distinct'],
        'instruction' => ['required', 'string', 'min:10', 'max:1000'],
        'confirmed' => ['accepted'],
    ];

    protected array $messages = [
        'selectedMemberIds.required' => 'Pilih minimal satu anggota Tim Rilis sebagai penerima disposisi.',
        'selectedMemberIds.array' => 'Pilihan penerima disposisi tidak valid.',
        'selectedMemberIds.min' => 'Pilih minimal satu anggota Tim Rilis sebagai penerima disposisi.',
        'selectedMemberIds.*.integer' => 'Pilihan penerima disposisi tidak valid.',
        'selectedMemberIds.*.distinct' => 'Anggota Tim Rilis yang sama tidak boleh dipilih dua kali.',
        'instruction.required' => 'Instruksi disposisi wajib diisi.',
        'instruction.string' => 'Instruksi disposisi harus berupa teks.',
        'instruction.min' => 'Instruksi disposisi minimal 10 karakter.',
        'instruction.max' => 'Instruksi disposisi maksimal 1.000 karakter.',
        'confirmed.accepted' => 'Kotak konfirmasi wajib dicentang sebelum disposisi dikirim.',
    ];

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('assignRelease', $rfc);

        $this->rfc = $rfc->load([
            'application.opd',
            'submittedBy.opd',
            'riskAssessment',
            'decisions.recordedBy',
            'attachments',
        ]);
    }

    public function assign(RfcDispositionService $service): void
    {
        $this->authorize('assignRelease', $this->rfc);

        $this->resetErrorBag();
        $validated = $this->validate();

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $service->assign(
                $this->rfc,
                $actor,
                $validated['selectedMemberIds'],
                $validated['instruction'],
            );
        } catch (RfcDispositionException $exception) {
            $this->addError('disposition', $exception->getMessage());

            return;
        }

        session()->flash(
            'success',
            "RFC {$this->rfc->displayIdentifier()} berhasil didisposisikan ke Tim Rilis."
        );

        $this->redirect(route('disposition.index', ['tab' => 'assigned']), navigate: true);
    }

    public function render(): View
    {
        // Otorisasi diulang agar request Livewire lanjutan tidak melewati policy.
        $this->authorize('assignRelease', $this->rfc);

        return view('livewire.disposition.assign', [
            'eligibleMembers' => $this->eligibleMembers(),
        ])->title('Disposisi RFC - '.$this->rfc->displayIdentifier());
    }

    /**
     * @return Collection<int, ReleaseTeamMember>
     */
    private function eligibleMembers(): Collection
    {
        return ReleaseTeamMember::query()
            ->with(['user.roles'])
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->whereHas('user', function (Builder $query): void {
                $query->where('is_active', true)
                    ->whereHas('roles', fn (Builder $role): Builder => $role->where('name', 'Tim Rilis'));
            })
            ->orderBy('name')
            ->get();
    }
}
