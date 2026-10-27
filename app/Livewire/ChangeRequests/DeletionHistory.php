<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcDeletionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('layouts.app')]
class DeletionHistory extends Component
{
    use WithPagination;

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $statusFilter = '';

    public bool $showRestoreModal = false;

    public ?int $selectedRfcId = null;

    public string $feedback = '';

    public string $errorMessage = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->validate(['dateFrom' => 'nullable|date']);
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->validate(['dateTo' => 'nullable|date']);
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'dateFrom', 'dateTo', 'statusFilter');
        $this->resetPage();
    }

    public function confirmRestore(int $rfcId): void
    {
        $rfc = $this->scopedQuery()->findOrFail($rfcId);

        $this->authorize('restore', $rfc);

        $this->reset('feedback', 'errorMessage');
        $this->selectedRfcId = $rfc->id;
        $this->showRestoreModal = true;
    }

    public function closeRestoreModal(): void
    {
        $this->showRestoreModal = false;
        $this->selectedRfcId = null;
        $this->reset('errorMessage');
    }

    public function restoreRfc(): void
    {
        if (! $this->selectedRfcId) {
            return;
        }

        $rfc = $this->scopedQuery()->find($this->selectedRfcId);

        if (! $rfc) {
            $this->errorMessage = 'RFC tidak ditemukan pada Riwayat Penghapusan.';
            $this->showRestoreModal = false;

            return;
        }

        $this->authorize('restore', $rfc);

        try {
            RfcDeletionService::restore($rfc, Auth::user());
        } catch (RuntimeException $exception) {
            $this->errorMessage = $exception->getMessage();

            return;
        }

        $this->showRestoreModal = false;
        $this->selectedRfcId = null;
        $this->feedback = "RFC {$rfc->displayIdentifier()} berhasil dipulihkan ke daftar RFC aktif.";
        $this->resetPage();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $deletedRfcs = $this->scopedQuery()
            ->with(['application', 'submittedBy.opd', 'deletedBy'])
            ->when($this->search !== '', function (Builder $query): void {
                $draftId = ChangeRequest::draftIdFromSearch($this->search);

                $query->where(fn (Builder $sub) => $sub
                    ->where('rfc_number', 'like', "%{$this->search}%")
                    ->orWhere('title', 'like', "%{$this->search}%")
                    ->when($draftId !== null, fn (Builder $draftQuery) => $draftQuery->orWhere(
                        fn (Builder $draft) => $draft
                            ->whereKey($draftId)
                            ->where('status', RfcStatus::DRAFT->value)
                    )));
            })
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('deleted_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('deleted_at', '<=', $this->dateTo))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('deleted_at')
            ->paginate(10);

        $selectedRfc = $this->selectedRfcId
            ? $this->scopedQuery()->with(['application', 'submittedBy'])->find($this->selectedRfcId)
            : null;

        return view('livewire.change-requests.deletion-history', [
            'deletedRfcs' => $deletedRfcs,
            'selectedRfc' => $selectedRfc,
            'statuses' => collect(RfcStatus::cases())->mapWithKeys(
                fn (RfcStatus $status) => [$status->value => $status->label()]
            ),
            'isStaff' => $user->can('rfc.view_all'),
        ])->title('Riwayat Penghapusan');
    }

    private function scopedQuery()
    {
        /** @var User $user */
        $user = Auth::user();

        return ChangeRequest::onlyTrashed()
            ->when(! $user->can('rfc.view_all'), fn ($query) => $query->where('submitted_by_id', $user->id));
    }
}
