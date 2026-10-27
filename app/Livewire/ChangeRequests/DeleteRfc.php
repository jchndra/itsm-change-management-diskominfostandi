<?php

namespace App\Livewire\ChangeRequests;

use App\Models\ChangeRequest;
use App\Services\RfcDeletionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

class DeleteRfc extends Component
{
    public ChangeRequest $rfc;

    public string $variant = 'menu';

    public bool $showModal = false;

    public string $reason = '';

    public string $errorMessage = '';

    public function mount(ChangeRequest $rfc, string $variant = 'menu'): void
    {
        $this->rfc = $rfc;
        $this->variant = $variant;
    }

    public function openModal(): void
    {
        $this->authorize('delete', $this->rfc);

        $this->reset('reason', 'errorMessage');
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset('reason', 'errorMessage');
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->rfc);

        $this->validate([
            'reason' => 'nullable|string|max:1000',
        ], [
            'reason.max' => 'Alasan penghapusan maksimal 1000 karakter.',
        ]);

        try {
            RfcDeletionService::delete($this->rfc, Auth::user(), $this->reason);
        } catch (RuntimeException $exception) {
            $this->errorMessage = $exception->getMessage();

            return;
        }

        $rfcIdentifier = $this->rfc->displayIdentifier();
        $this->showModal = false;
        $this->reset('reason', 'errorMessage');

        $this->dispatch(
            'rfc-deleted',
            message: "RFC {$rfcIdentifier} berhasil dihapus dan dipindahkan ke Riwayat Penghapusan."
        );
    }

    public function render()
    {
        return view('livewire.change-requests.delete-rfc');
    }
}
