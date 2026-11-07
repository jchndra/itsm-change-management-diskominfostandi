<?php

namespace App\Livewire\ChangeRequests;

use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Exceptions\ReleaseTaskProgressException;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\User;
use App\Services\ReleaseTaskProgressService;
use Livewire\Component;

class ShowChangeRequest extends Component
{
    public ChangeRequest $rfc;

    public string $estimatedDays = '';

    public string $revisedDays = '';

    public string $revisionReason = '';

    public string $progressNote = '';

    public bool $progressBlocked = false;

    public string $progressMessage = '';

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

    /**
     * Dipanggil oleh wire:poll agar riwayat proses mengikuti aksi role lain.
     */
    public function refreshProcessHistory(): void
    {
        $this->authorize('view', $this->rfc);

        $this->rfc->refresh();
    }

    public function setEstimate(): void
    {
        $this->authorize('view', $this->rfc);
        $this->resetProgressFeedback();

        $task = $this->assignedTask();

        if ($task === null) {
            $this->addError('progress', 'Tugas Tim Rilis tidak ditemukan.');

            return;
        }

        try {
            app(ReleaseTaskProgressService::class)
                ->setEstimate($task, $this->currentUser(), (int) $this->estimatedDays);
        } catch (ReleaseTaskProgressException|InvalidRfcStatusTransitionException $exception) {
            $this->addError('progress', $exception->getMessage());

            return;
        }

        $this->estimatedDays = '';
        $this->progressMessage = 'Estimasi pengerjaan berhasil disimpan.';
        $this->refreshProgressData();
    }

    public function reviseEstimate(): void
    {
        $this->authorize('view', $this->rfc);
        $this->resetProgressFeedback();

        $task = $this->assignedTask();

        if ($task === null) {
            $this->addError('progress', 'Tugas Tim Rilis tidak ditemukan.');

            return;
        }

        try {
            app(ReleaseTaskProgressService::class)
                ->reviseEstimate($task, $this->currentUser(), (int) $this->revisedDays, $this->revisionReason);
        } catch (ReleaseTaskProgressException|InvalidRfcStatusTransitionException $exception) {
            $this->addError('progress', $exception->getMessage());

            return;
        }

        $this->revisedDays = '';
        $this->revisionReason = '';
        $this->progressMessage = 'Revisi estimasi berhasil disimpan.';
        $this->refreshProgressData();
    }

    public function startWork(): void
    {
        $this->authorize('view', $this->rfc);
        $this->resetProgressFeedback();

        $task = $this->assignedTask();

        if ($task === null) {
            $this->addError('progress', 'Tugas Tim Rilis tidak ditemukan.');

            return;
        }

        try {
            app(ReleaseTaskProgressService::class)->start($task, $this->currentUser());
        } catch (ReleaseTaskProgressException|InvalidRfcStatusTransitionException $exception) {
            $this->addError('progress', $exception->getMessage());

            return;
        }

        $this->progressMessage = 'Pengerjaan berhasil dimulai.';
        $this->refreshProgressData();
    }

    public function addProgressNote(): void
    {
        $this->authorize('view', $this->rfc);
        $this->resetProgressFeedback();

        $task = $this->assignedTask();

        if ($task === null) {
            $this->addError('progress', 'Tugas Tim Rilis tidak ditemukan.');

            return;
        }

        try {
            app(ReleaseTaskProgressService::class)
                ->addNote($task, $this->currentUser(), $this->progressNote, $this->progressBlocked);
        } catch (ReleaseTaskProgressException|InvalidRfcStatusTransitionException $exception) {
            $this->addError('progress', $exception->getMessage());

            return;
        }

        $this->progressNote = '';
        $this->progressBlocked = false;
        $this->progressMessage = 'Catatan kegiatan berhasil ditambahkan.';
        $this->refreshProgressData();
    }

    private function assignedTask(): ?ReleaseTask
    {
        return $this->rfc->releaseTasks->first(
            fn (ReleaseTask $task): bool => $task->releaseTeamMember?->user_id === auth()->id()
        );
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function resetProgressFeedback(): void
    {
        $this->resetValidation('progress');
        $this->progressMessage = '';
    }

    private function refreshProgressData(): void
    {
        $this->rfc->refresh();
        $this->rfc->load([
            'releaseTasks.releaseTeamMember',
            'releaseTasks.assignedBy',
            'releaseTasks.updates.user',
        ]);
    }

    public function render()
    {
        return view('livewire.change-requests.show-change-request')->title('Detail RFC - '.$this->rfc->displayIdentifier());
    }
}
