<?php

namespace App\Livewire\ChangeRequests;

use App\Exceptions\RfcSatisfactionException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcSatisfactionService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ShowChangeRequest extends Component
{
    public ChangeRequest $rfc;

    public int $satisfactionRating = 0;

    public string $satisfactionComment = '';

    public string $satisfactionMessage = '';

    public function mount(ChangeRequest $rfc)
    {
        // Otorisasi detail RFC mengikuti ChangeRequestPolicy::view().
        $this->authorize('view', $rfc);

        $user = auth()->user();
        $canViewAll = $user?->can('rfc.view_all') ?? false;
        $isAssignedReleaseTeam = ! $canViewAll
            && ($user?->isReleaseTeam() ?? false)
            && $rfc->hasReleaseTaskFor($user);
        $isRequesterOwner = $user !== null && $rfc->submitted_by_id === $user->id;

        // Muat relasi yang dibutuhkan untuk halaman detail
        $relations = [
            'submittedBy.opd',
            'application.opd',
            'attachments',
            'riskAssessment',
            'decisions.authority',
            'implementation.evaluatedBy',
            'evidences',
            'closedBy',
            'satisfaction',
        ];

        // Disposisi hanya dimuat untuk staf dan penerima disposisi pada RFC ini.
        // Pemohon dan Tim Rilis lain tidak boleh menerima instruksi maupun nama
        // penerima melalui properti komponen.
        if ($canViewAll || $isAssignedReleaseTeam || $isRequesterOwner) {
            $relations[] = 'releaseTasks.releaseTeamMember';
            $relations[] = 'releaseTasks.assignedBy';
            $relations[] = 'releaseTasks.updates.user';
        }

        $this->rfc = $rfc->load($relations);
    }

    public function submitSatisfaction(): void
    {
        $this->authorize('rate', $this->rfc);
        $this->resetValidation(['satisfactionRating', 'satisfactionComment']);
        $this->satisfactionMessage = '';

        $validated = $this->validate([
            'satisfactionRating' => 'required|integer|between:1,5',
            'satisfactionComment' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn (): bool => $this->satisfactionRating >= 1 && $this->satisfactionRating <= 4),
                Rule::when(
                    $this->satisfactionRating >= 1 && $this->satisfactionRating <= 4,
                    ['min:5']
                ),
            ],
        ], [
            'satisfactionRating.required' => 'Pilih jumlah bintang penilaian terlebih dahulu.',
            'satisfactionRating.integer' => 'Penilaian harus berupa angka bintang.',
            'satisfactionRating.between' => 'Penilaian harus antara 1 sampai 5 bintang.',
            'satisfactionComment.string' => 'Komentar harus berupa teks.',
            'satisfactionComment.max' => 'Komentar penilaian maksimal 1000 karakter.',
            'satisfactionComment.required' => 'Komentar wajib diisi untuk penilaian 1 sampai 4 bintang.',
            'satisfactionComment.min' => 'Komentar minimal 5 karakter.',
        ]);

        try {
            app(RfcSatisfactionService::class)->submit(
                $this->rfc,
                $this->currentUser(),
                (int) $validated['satisfactionRating'],
                $validated['satisfactionComment'] ?? null,
            );
        } catch (RfcSatisfactionException $exception) {
            $this->addError('satisfactionRating', $exception->getMessage());

            return;
        }

        $this->satisfactionRating = 0;
        $this->satisfactionComment = '';
        $this->satisfactionMessage = 'Penilaian layanan berhasil dikirim.';
        $this->rfc->refresh();
        $this->rfc->load('satisfaction');
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render()
    {
        return view('livewire.change-requests.show-change-request')->title('Detail RFC - '.$this->rfc->displayIdentifier());
    }
}
