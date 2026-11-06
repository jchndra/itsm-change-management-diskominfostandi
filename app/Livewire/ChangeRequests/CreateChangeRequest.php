<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\Concerns\ValidatesChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcAttachmentService;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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

    public string $legal_basis = '';

    public ?string $risk_event = null;

    public string $reason_for_change = '';

    public string $change_risk = '';

    public string $impact_description = '';

    public bool $head_of_department_approved = false;

    /** @var array<int, UploadedFile> */
    public array $attachments = [];

    /**
     * Mencegah dua klik submit membuat dua RFC dari form yang sama.
     */
    #[Locked]
    public string $submissionToken = '';

    public function mount(): void
    {
        $this->authorize('create', ChangeRequest::class);
        $this->submissionToken = (string) Str::uuid();
    }

    public function save(string $targetStatus = RfcStatus::DRAFT->value): void
    {
        $this->authorize('create', ChangeRequest::class);
        $this->validateChangeRequest();

        $isSubmission = in_array($targetStatus, [
            RfcStatus::SUBMITTED->value,
            RfcStatus::TICKET_CREATED->value,
        ], true);

        if ($isSubmission) {
            $this->validateForSubmission();
        }

        /** @var User $user */
        $user = Auth::user();

        $rfc = null;
        $newAttachments = [];
        $attempt = 0;

        while ($rfc === null) {
            $newAttachments = [];

            try {
                $rfc = DB::transaction(function () use ($user, $isSubmission, &$newAttachments): ChangeRequest {
                    $attributes = [
                        'title' => $this->title,
                        'application_id' => $this->application_id,
                        'submitted_by_id' => $user->id,
                        'created_by_id' => $user->id,
                        'change_type' => $this->change_type,
                        'description' => $this->description,
                        'legal_basis' => $this->legal_basis,
                        'risk_event' => filled($this->risk_event) ? $this->risk_event : null,
                        'reason_for_change' => $this->reason_for_change,
                        'change_risk' => $this->change_risk,
                        'head_of_department_approved' => $this->head_of_department_approved,
                        'head_of_department_approved_at' => $this->head_of_department_approved ? now() : null,
                        'impact_description' => filled($this->impact_description) ? $this->impact_description : null,
                        'emergency_unconfirmed' => $this->change_type === 'emergency',
                        'status' => RfcStatus::DRAFT->value,
                        'submission_token' => $this->submissionToken,
                    ];

                    $rfc = ChangeRequest::create($attributes);

                    if ($isSubmission) {
                        RfcStatusTransition::transitionTo($rfc, RfcStatus::TICKET_CREATED, $user);
                    } else {
                        activity('rfc')
                            ->performedOn($rfc)
                            ->causedBy($user)
                            ->withProperties([
                                'old_status' => null,
                                'new_status' => RfcStatus::DRAFT->value,
                                'note' => null,
                            ])
                            ->log('Draft RFC disimpan');
                    }

                    $newAttachments = app(RfcAttachmentService::class)
                        ->storeMany($rfc, $this->attachments, $user);

                    return $rfc;
                });
            } catch (UniqueConstraintViolationException $exception) {
                app(RfcAttachmentService::class)->deleteStoredFiles($newAttachments);

                $existingRfc = ChangeRequest::withTrashed()
                    ->where('submission_token', $this->submissionToken)
                    ->first();

                if ($existingRfc !== null) {
                    $this->attachments = [];

                    session()->flash('message', "Permohonan {$existingRfc->displayIdentifier()} sudah tercatat.");

                    $this->redirectAfterSave($user);

                    return;
                }

                $attempt++;

                if ($attempt >= 3) {
                    throw $exception;
                }
            } catch (Throwable $exception) {
                app(RfcAttachmentService::class)->deleteStoredFiles($newAttachments);

                throw $exception;
            }
        }

        $this->attachments = [];

        $statusMessage = $isSubmission
            ? 'berhasil diajukan dan diteruskan ke Change Manager'
            : 'berhasil disimpan sebagai draft';
        session()->flash('message', "{$rfc->displayIdentifier()} {$statusMessage}.");

        $this->redirectAfterSave($user);
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

    private function redirectAfterSave(User $user): void
    {
        $this->redirect(
            $user->isStaff() ? route('change-requests.index') : route('requester.dashboard'),
            navigate: true
        );
    }
}
