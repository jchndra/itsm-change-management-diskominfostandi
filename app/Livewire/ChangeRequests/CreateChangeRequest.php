<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\Concerns\ValidatesChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcNumberGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class CreateChangeRequest extends Component
{
    use ValidatesChangeRequest;
    use WithFileUploads;

    public string $title = '';
    public string $application_id = '';
    public string $reason_for_change = '';
    public string $impact_description = '';
    public string $target_implementation_date = '';
    public bool $is_physical_letter = false;
    public string $physical_letter_number = '';

    public function mount(): void
    {
        $this->authorize('create', ChangeRequest::class);
    }

    public function save(string $targetStatus = RfcStatus::DRAFT->value): void
    {
        $this->authorize('create', ChangeRequest::class);

        $this->validateChangeRequest();

        /** @var User $user */
        $user = Auth::user();

        $rfcNumber = RfcNumberGenerator::generate();

        $status = $targetStatus === RfcStatus::SUBMITTED->value
            ? RfcStatus::SUBMITTED
            : RfcStatus::DRAFT;

        $rfc = ChangeRequest::create([
            'rfc_number' => $rfcNumber,
            'title' => $this->title,
            'application_id' => $this->application_id,
            'submitted_by_id' => $user->id,
            'created_by_id' => $user->id,
            'reason_for_change' => $this->reason_for_change,
            'impact_description' => $this->impact_description,
            'target_implementation_date' => $this->target_implementation_date,
            'is_physical_letter' => $this->is_physical_letter,
            'physical_letter_number' => $this->is_physical_letter ? $this->physical_letter_number : null,
            'status' => $status->value,
        ]);

        activity('rfc')
            ->performedOn($rfc)
            ->causedBy($user)
            ->withProperties([
                'old_status' => null,
                'new_status' => $status->value,
                'note' => null,
            ])
            ->log($status === RfcStatus::SUBMITTED ? 'RFC resmi diajukan' : 'Draft RFC disimpan');

        $statusMessage = $status === RfcStatus::SUBMITTED ? 'berhasil diajukan' : 'berhasil disimpan sebagai draft';
        session()->flash('message', "Permohonan {$rfc->rfc_number} {$statusMessage}.");

        $this->redirect(
            $user->isStaff() ? route('change-requests.index') : route('requester.dashboard'),
            navigate: true
        );
    }

    public function render(): View
    {
        $applications = Application::with('opd')->orderBy('name')->get();

        return view('livewire.change-requests.create-change-request', [
            'applications' => $applications,
        ]);
    }
}
