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

    public string $activityPlan = '';

    public string $changePlan = '';

    public string $rollbackMechanism = '';

    public bool $showRiskForm = false;

    /** @var array<int, array{identifier: string, range: string}> */
    public array $scheduleConflicts = [];

    public string $revisedDays = '';

    public string $revisionReason = '';

    public bool $showUpdateForm = false;

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
            'releaseTasks.riskAnalysis.submittedBy',
        ]);

        $task = $this->assignedTask();

        $this->plannedStartOn = $task?->planned_start_on?->toDateString()
            ?? now('Asia/Jakarta')->toDateString();

        if ($task?->estimated_days !== null) {
            $this->estimatedDays = (string) $task->estimated_days;
        }

        if ($task?->riskAnalysis !== null) {
            $this->activityPlan = $task->riskAnalysis->activity_plan;
            $this->changePlan = $task->riskAnalysis->change_plan;
            $this->rollbackMechanism = $task->riskAnalysis->rollback_mechanism;
        }

        $this->showRiskForm = $task?->riskAnalysis === null;
    }

    public function toggleRiskForm(): void
    {
        $this->authorize('view', $this->rfc);

        $this->showRiskForm = ! $this->showRiskForm;
        $this->resetFeedback();
    }

    public function saveRiskAnalysis(): void
    {
        $this->authorize('view', $this->rfc);
        $this->resetFeedback();

        $task = $this->assignedTask();

        if ($task === null) {
            $this->addError('progress', 'Tugas Tim Rilis tidak ditemukan.');

            return;
        }

        $hasEstimate = $task->estimated_days !== null;

        $this->validate([
            'estimatedDays' => $hasEstimate ? ['nullable'] : ['required', 'integer', 'between:1,90'],
            'plannedStartOn' => ['nullable', 'date_format:Y-m-d'],
            'activityPlan' => ['required', 'string', 'min:10', 'max:2000'],
            'changePlan' => ['required', 'string', 'min:10', 'max:2000'],
            'rollbackMechanism' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'estimatedDays.required' => 'Estimasi kegiatan wajib diisi.',
            'estimatedDays.integer' => 'Estimasi kegiatan harus berupa angka.',
            'estimatedDays.between' => 'Estimasi kegiatan harus antara 1 dan 90 hari.',
            'plannedStartOn.date_format' => 'Format tanggal rencana mulai tidak valid.',
            'activityPlan.required' => 'Rencana kegiatan wajib diisi.',
            'activityPlan.min' => 'Rencana kegiatan minimal 10 karakter.',
            'activityPlan.max' => 'Rencana kegiatan maksimal 2.000 karakter.',
            'changePlan.required' => 'Rencana perubahan wajib diisi.',
            'changePlan.min' => 'Rencana perubahan minimal 10 karakter.',
            'changePlan.max' => 'Rencana perubahan maksimal 2.000 karakter.',
            'rollbackMechanism.required' => 'Mekanisme rollback wajib diisi.',
            'rollbackMechanism.min' => 'Mekanisme rollback minimal 10 karakter.',
            'rollbackMechanism.max' => 'Mekanisme rollback maksimal 2.000 karakter.',
        ]);

        $plannedStart = $this->parsePlannedStart();

        if ($plannedStart === false) {
            return;
        }

        try {
            app(ReleaseTaskProgressService::class)->saveRiskAnalysis(
                $task,
                $this->currentUser(),
                [
                    'activity_plan' => $this->activityPlan,
                    'change_plan' => $this->changePlan,
                    'rollback_mechanism' => $this->rollbackMechanism,
                ],
                (int) ($hasEstimate ? $task->estimated_days : $this->estimatedDays),
                $plannedStart,
            );
        } catch (ReleaseTaskProgressException|InvalidRfcStatusTransitionException $exception) {
            $this->addError('progress', $exception->getMessage());

            return;
        }

        $this->progressMessage = 'Analisis risiko berhasil disimpan.';
        $this->showRiskForm = false;
        $this->refreshProgressData();
        $this->captureScheduleConflicts($task);
        $this->dispatch('release-progress-updated');
    }

    public function reviseEstimate(): void
    {
        $this->authorize('view', $this->rfc);
        $this->resetFeedback();

        $task = $this->assignedTask();

        if ($task === null) {
            $this->addError('progress', 'Tugas Tim Rilis tidak ditemukan.');

            return;
        }

        $this->validate([
            'revisedDays' => ['required', 'integer', 'between:1,90'],
            'revisionReason' => ['required', 'string', 'min:'.ReleaseTaskProgressService::MIN_REASON_LENGTH, 'max:'.ReleaseTaskProgressService::MAX_REASON_LENGTH],
        ], [
            'revisedDays.required' => 'Estimasi hari baru wajib diisi.',
            'revisedDays.integer' => 'Estimasi hari baru harus berupa angka.',
            'revisedDays.between' => 'Estimasi hari baru harus antara 1 dan 90 hari.',
            'revisionReason.required' => 'Alasan pembaruan informasi wajib diisi.',
            'revisionReason.min' => 'Alasan pembaruan informasi minimal '.ReleaseTaskProgressService::MIN_REASON_LENGTH.' karakter.',
            'revisionReason.max' => 'Alasan pembaruan informasi maksimal '.ReleaseTaskProgressService::MAX_REASON_LENGTH.' karakter.',
        ]);

        try {
            app(ReleaseTaskProgressService::class)
                ->reviseEstimate($task, $this->currentUser(), (int) $this->revisedDays, $this->revisionReason);
        } catch (ReleaseTaskProgressException|InvalidRfcStatusTransitionException $exception) {
            $this->addError('progress', $exception->getMessage());

            return;
        }

        $this->revisedDays = '';
        $this->revisionReason = '';
        $this->showUpdateForm = false;
        $this->progressMessage = 'Update tersimpan.';
        $this->refreshProgressData();
        $this->dispatch('release-progress-updated');
    }

    public function startWork(): void
    {
        $this->authorize('view', $this->rfc);
        $this->resetFeedback();

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
        $this->resetFeedback();

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
        $this->progressMessage = 'Rencana perubahan berhasil ditambahkan.';
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

    private function parsePlannedStart(): Carbon|false|null
    {
        if (blank($this->plannedStartOn)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $this->plannedStartOn, 'Asia/Jakarta')->startOfDay();
        } catch (\Throwable) {
            $this->addError('plannedStartOn', 'Format rencana mulai tidak valid.');

            return false;
        }
    }

    private function resetFeedback(): void
    {
        $this->resetValidation();
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
            'releaseTasks.riskAnalysis.submittedBy',
        ]);
    }
}
