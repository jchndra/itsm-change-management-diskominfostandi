<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\Concerns\ValidatesChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcAttachmentService;
use App\Services\RfcNumberGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.app')]
class CreateChangeRequest extends Component
{
    use ValidatesChangeRequest;
    use WithFileUploads;

    public string $title = '';

    public string $application_id = '';

    public string $change_type = '';

    public string $description = '';

    public string $reason_for_change = '';

    public string $impact_description = '';

    public string $target_implementation_date = '';

    public bool $is_physical_letter = false;

    public string $physical_letter_number = '';

    /** @var array<int, UploadedFile> */
    public array $attachments = [];

    public function mount(): void
    {
        $this->authorize('create', ChangeRequest::class);
    }

    public function save(string $targetStatus = RfcStatus::DRAFT->value): void
    {
        $this->authorize('create', ChangeRequest::class);
        $this->validateChangeRequest();

        $status = $targetStatus === RfcStatus::SUBMITTED->value
            ? RfcStatus::SUBMITTED
            : RfcStatus::DRAFT;

        if ($status === RfcStatus::SUBMITTED) {
            $this->validatePhysicalLetterAttachment();
        }

        /** @var User $user */
        $user = Auth::user();

        $rfcNumber = RfcNumberGenerator::generate();
        $newAttachments = [];

        try {
            $rfc = DB::transaction(function () use ($user, $rfcNumber, $status, &$newAttachments): ChangeRequest {
                $rfc = ChangeRequest::create([
                    'rfc_number' => $rfcNumber,
                    'title' => $this->title,
                    'application_id' => $this->application_id,
                    'submitted_by_id' => $user->id,
                    'created_by_id' => $user->id,
                    'change_type' => $this->change_type,
                    'description' => $this->description,
                    'reason_for_change' => $this->reason_for_change,
                    'impact_description' => filled($this->impact_description) ? $this->impact_description : null,
                    'target_implementation_date' => $this->target_implementation_date,
                    'is_physical_letter' => $this->is_physical_letter,
                    'physical_letter_number' => $this->is_physical_letter ? $this->physical_letter_number : null,
                    'emergency_unconfirmed' => $this->change_type === 'emergency',
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

                $newAttachments = app(RfcAttachmentService::class)->storeMany($rfc, $this->attachments, $user);

                return $rfc;
            });
        } catch (Throwable $exception) {
            app(RfcAttachmentService::class)->deleteStoredFiles($newAttachments);

            throw $exception;
        }

        $this->attachments = [];

        $statusMessage = $status === RfcStatus::SUBMITTED ? 'berhasil diajukan' : 'berhasil disimpan sebagai draft';
        session()->flash('message', "Permohonan {$rfc->rfc_number} {$statusMessage}.");

        $this->redirect(
            $user->isStaff() ? route('change-requests.index') : route('requester.dashboard'),
            navigate: true
        );
    }

    public function render(): View
    {
        /** @var User $requester */
        $requester = Auth::user();
        $requester->loadMissing('opd');

        $applications = Application::with('opd')->orderBy('name')->get();

        return view('livewire.change-requests.create-change-request', [
            'applications' => $applications,
            'requester' => $requester,
            'submittedAt' => now(),
        ]);
    }
}
