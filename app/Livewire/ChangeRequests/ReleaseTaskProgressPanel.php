<?php

namespace App\Livewire\ChangeRequests;

use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Exceptions\ReleaseTaskProgressException;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\User;
use App\Services\ReleaseTaskProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

class ReleaseTaskProgressPanel extends Component
{
    public ChangeRequest $rfc;

    public string $estimatedDays = '';

    public string $plannedStartOn = '';

    /** @var array<int, array{identifier: string, range: string}> */
    public array $scheduleConflicts = [];

    public string $revisedDays = '';

    public string $revisionReason = '';

    public string $progressNote = '';

    public bool $progressBlocked = false;

    public string $progressMessage = '';

    public function mount(ChangeRequest $rfc): void
    {
        // Otorisasi tetap mengikuti ChangeRequestPolicy::view().
        $this->authorize('view', $rfc);

        $this->rfc = $rfc->load([
            'releaseTasks.releaseTeamMember',
            'releaseTasks.assignedBy',
            'releaseTasks.updates.user',
        ]);

        $this->plannedStartOn = $this->assignedTask()?->planned_start_on?->toDateString()
            ?? now('Asia/Jakarta')->toDateString();
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

        $plannedStart = null;

        if (filled($this->plannedStartOn)) {
            try {
                $plannedStart = Carbon::createFromFormat('Y-m-d', $this->plannedStartOn, 'Asia/Jakarta')->startOfDay();
            } catch (\Throwable) {
                $this->addError('progress', 'Format rencana mulai tidak valid.');

                return;
            }
        }

        try {
            app(ReleaseTaskProgressService::class)
                ->setEstimate($task, $this->currentUser(), (int) $this->estimatedDays, $plannedStart);
        } catch (ReleaseTaskProgressException|InvalidRfcStatusTransitionException $exception) {
            $this->addError('progress', $exception->getMessage());

            return;
        }

        $this->estimatedDays = '';
        $this->progressMessage = 'Estimasi pengerjaan berhasil disimpan.';
        $this->refreshProgressData();
        $this->captureScheduleConflicts($task);
        $this->dispatch('release-progress-updated');
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
        $this->dispatch('release-progress-updated');
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
        $this->dispatch('release-progress-updated');
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
        $this->dispatch('release-progress-updated');
    }

    public function render(): View
    {
        $this->authorize('view', $this->rfc);

        return view('livewire.change-requests.release-task-progress-panel', [
            'progressTask' => $this->assignedTask(),
        ]);
    }

    private function assignedTask(): ?ReleaseTask
    {
        $this->rfc->loadMissing('releaseTasks.releaseTeamMember');

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
        $this->scheduleConflicts = [];
    }

    private function captureScheduleConflicts(ReleaseTask $task): void
    {
        $freshTask = $task->fresh(['changeRequest']);
        $window = $freshTask?->scheduleWindow();

        if ($freshTask === null || $window === null) {
            $this->scheduleConflicts = [];

            return;
        }

        [$start, $end] = $window;

        $this->scheduleConflicts = app(ReleaseTaskProgressService::class)
            ->conflictsFor($freshTask, $start, $end)
            ->map(fn (ReleaseTask $conflict): array => [
                'identifier' => $conflict->changeRequest?->displayIdentifier() ?? 'RFC lain',
                'range' => $this->formatConflictRange($conflict->scheduleWindow()),
            ])
            ->all();
    }

    /**
     * @param  array{0: Carbon, 1: Carbon}|null  $window
     */
    private function formatConflictRange(?array $window): string
    {
        if ($window === null) {
            return '-';
        }

        [$start, $end] = $window;
        $start->locale('id');
        $end->locale('id');

        if ($start->format('Y-m') === $end->format('Y-m')) {
            return $start->translatedFormat('d').'-'.$end->translatedFormat('d M');
        }

        return $start->translatedFormat('d M').' - '.$end->translatedFormat('d M');
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
}
