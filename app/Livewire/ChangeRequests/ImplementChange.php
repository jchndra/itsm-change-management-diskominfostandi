<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\FollowUpAction;
use App\Enums\ImplementationResult;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Exceptions\RfcImplementationException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcImplementationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ImplementChange extends Component
{
    use WithFileUploads;

    public ChangeRequest $rfc;

    public string $implementation_result = '';

    public string $started_at = '';

    public string $finished_at = '';

    public string $executor_name = '';

    public string $report_source = '';

    public string $summary = '';

    public string $test_results = '';

    public string $failure_notes = '';

    public string $follow_up_action = '';

    public string $follow_up_notes = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    public bool $confirmed = false;

    protected function rules(): array
    {
        $isFailure = in_array($this->implementation_result, [
            ImplementationResult::FAILED->value,
            ImplementationResult::ROLLED_BACK->value,
        ], true);

        return [
            'implementation_result' => ['required', Rule::in(ImplementationResult::values())],
            'started_at' => ['required', 'date', 'before_or_equal:now'],
            'finished_at' => ['required', 'date', 'after_or_equal:started_at', 'before_or_equal:now'],
            'executor_name' => ['required', 'string', 'max:255'],
            'report_source' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'test_results' => ['required', 'string'],
            'failure_notes' => $isFailure ? ['required', 'string'] : ['nullable', 'string'],
            'follow_up_action' => $isFailure
                ? ['required', Rule::in(FollowUpAction::values())]
                : ['nullable'],
            'follow_up_notes' => [
                Rule::requiredIf($isFailure && $this->follow_up_action !== FollowUpAction::NONE->value),
                'nullable',
                'string',
            ],
            'files' => ['required', 'array', 'min:1', 'max:'.RfcImplementationService::MAX_FILES],
            'files.*' => ['file', 'max:5120', 'mimetypes:application/pdf,image/png,image/jpeg,image/jpg'],
            'confirmed' => ['accepted'],
        ];
    }

    protected array $messages = [
        'implementation_result.required' => 'Hasil implementasi wajib dipilih.',
        'implementation_result.in' => 'Hasil implementasi tidak dikenali.',
        'started_at.required' => 'Waktu mulai pelaksanaan wajib diisi.',
        'started_at.date' => 'Waktu mulai pelaksanaan tidak valid.',
        'started_at.before_or_equal' => 'Waktu mulai pelaksanaan tidak boleh di masa depan.',
        'finished_at.required' => 'Waktu selesai pelaksanaan wajib diisi.',
        'finished_at.date' => 'Waktu selesai pelaksanaan tidak valid.',
        'finished_at.after_or_equal' => 'Waktu selesai tidak boleh lebih awal dari waktu mulai.',
        'finished_at.before_or_equal' => 'Waktu selesai pelaksanaan tidak boleh di masa depan.',
        'executor_name.required' => 'Pelaksana perubahan wajib diisi.',
        'executor_name.max' => 'Nama pelaksana perubahan maksimal 255 karakter.',
        'report_source.required' => 'Sumber laporan wajib diisi.',
        'report_source.max' => 'Sumber laporan maksimal 255 karakter.',
        'summary.required' => 'Ringkasan hasil wajib diisi.',
        'test_results.required' => 'Hasil pengujian wajib diisi.',
        'failure_notes.required' => 'Catatan kegagalan atau rollback wajib diisi.',
        'follow_up_action.required' => 'Pilihan tindak lanjut wajib ditentukan.',
        'follow_up_action.in' => 'Pilihan tindak lanjut tidak dikenali.',
        'follow_up_notes.required' => 'Keterangan tindak lanjut wajib diisi.',
        'files.required' => 'Minimal satu berkas bukti wajib diunggah.',
        'files.min' => 'Minimal satu berkas bukti wajib diunggah.',
        'files.max' => 'Jumlah berkas bukti maksimal 5 file.',
        'files.*.file' => 'Berkas bukti tidak valid.',
        'files.*.max' => 'Ukuran setiap berkas bukti maksimal 5 MB.',
        'files.*.mimetypes' => 'Berkas bukti harus berformat PDF, PNG, JPG, atau JPEG.',
        'confirmed.accepted' => 'Konfirmasi pemeriksaan data dan bukti wajib dicentang.',
    ];

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('implement', $rfc);

        $this->rfc = $rfc->load(['application.opd', 'submittedBy']);

        $result = request()->query('hasil');

        if (is_string($result) && in_array($result, ImplementationResult::values(), true)) {
            $this->implementation_result = $result;
        }
    }

    public function removeFile(int $index): void
    {
        if (! isset($this->files[$index])) {
            return;
        }

        $file = $this->files[$index];

        if (method_exists($file, 'delete')) {
            $file->delete();
        }

        unset($this->files[$index]);
        $this->files = array_values($this->files);
        $this->resetValidation('files');
        $this->resetValidation('files.*');
    }

    public function saveImplementation(): void
    {
        $this->authorize('implement', $this->rfc);

        $this->validate();

        /** @var User $user */
        $user = Auth::user();

        $isFailure = in_array($this->implementation_result, [
            ImplementationResult::FAILED->value,
            ImplementationResult::ROLLED_BACK->value,
        ], true);

        $data = [
            'implementation_result' => $this->implementation_result,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'executor_name' => $this->executor_name,
            'report_source' => $this->report_source,
            'summary' => $this->summary,
            'test_results' => $this->test_results,
            'failure_notes' => $isFailure ? $this->failure_notes : null,
            'follow_up_action' => $isFailure ? $this->follow_up_action : null,
            'follow_up_notes' => $isFailure ? $this->follow_up_notes : null,
        ];

        try {
            app(RfcImplementationService::class)->record($this->rfc, $user, $data, $this->files);
        } catch (InvalidRfcStatusTransitionException|RfcImplementationException $exception) {
            $this->addError('status', $exception->getMessage());

            return;
        }

        $message = $this->implementation_result === ImplementationResult::SUCCESS->value
            ? "Implementasi RFC {$this->rfc->displayIdentifier()} berhasil dicatat dan diselesaikan."
            : "Implementasi RFC {$this->rfc->displayIdentifier()} dicatat gagal / mengalami rollback.";

        session()->flash('message', $message);
        $this->redirect(route('change-requests.show', $this->rfc->id), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.change-requests.implement-change');
    }
}
