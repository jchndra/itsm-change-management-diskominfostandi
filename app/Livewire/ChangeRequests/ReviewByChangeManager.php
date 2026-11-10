<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\DecisionStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Exceptions\RfcDispositionException;
use App\Models\ChangeRequest;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\RfcChangeManagerReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ReviewByChangeManager extends Component
{
    public ChangeRequest $rfc;

    public string $risk_level = 'Rendah';

    public string $impact_level = 'Sedang';

    public string $urgency_level = 'Normal';

    public string $classification = 'Normal Change';

    public string $analysis_notes = '';

    public string $decisionStatus = DecisionStatus::APPROVED->value;

    public string $notes = '';

    public ?int $selectedMemberId = null;

    public string $instruction = '';

    public bool $confirmed = false;

    /**
     * @var array<string, string>
     */
    private array $valueMap = [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
        'critical' => 'Kritis',
        'emergency' => 'Emergency',
        'normal' => 'Normal',
        'standard' => 'Standard Change',
    ];

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('recordDecision', $rfc);
        $this->authorize('assessRisk', $rfc);

        $this->rfc = $rfc->load(['submittedBy.opd', 'application.opd', 'riskAssessment', 'attachments']);

        if ($this->rfc->riskAssessment) {
            $this->risk_level = $this->normalizeValue($this->rfc->riskAssessment->risk_level, 'Rendah');
            $this->impact_level = $this->normalizeValue($this->rfc->riskAssessment->impact_level, 'Sedang');
            $this->urgency_level = $this->normalizeValue($this->rfc->riskAssessment->urgency_level, 'Normal');
            $this->classification = $this->rfc->riskAssessment->classification ?? 'Normal Change';
            $this->analysis_notes = $this->rfc->riskAssessment->analysis_notes ?? '';
        } else {
            $this->classification = $this->classificationForChangeType($this->rfc->change_type);
        }
    }

    public function submit(RfcChangeManagerReviewService $service): void
    {
        // Otorisasi diulang agar request Livewire lanjutan tidak melewati policy.
        $this->authorize('recordDecision', $this->rfc);
        $this->authorize('assessRisk', $this->rfc);

        $this->resetErrorBag();

        $this->risk_level = $this->normalizeValue($this->risk_level, 'Rendah');
        $this->impact_level = $this->normalizeValue($this->impact_level, 'Sedang');
        $this->urgency_level = $this->normalizeValue($this->urgency_level, 'Normal');

        $this->validate();

        /** @var User $actor */
        $actor = Auth::user();
        $isApproved = $this->decisionStatus === DecisionStatus::APPROVED->value;

        try {
            $service->submit(
                $this->rfc,
                $actor,
                [
                    'risk_level' => $this->risk_level,
                    'impact_level' => $this->impact_level,
                    'urgency_level' => $this->urgency_level,
                    'classification' => $this->classification,
                    'analysis_notes' => $this->analysis_notes,
                ],
                $this->decisionStatus,
                $this->notes,
                $isApproved ? $this->selectedMemberId : null,
                $isApproved ? $this->instruction : null,
            );
        } catch (InvalidRfcStatusTransitionException $exception) {
            $this->addError('status', $exception->getMessage());

            return;
        } catch (RfcDispositionException $exception) {
            $this->addError('disposition', $exception->getMessage());

            return;
        }

        session()->flash('message', 'Tinjauan Change Manager berhasil disimpan.');
        $this->redirectRoute('change-requests.index');
    }

    public function render(): View
    {
        $this->authorize('recordDecision', $this->rfc);
        $this->authorize('assessRisk', $this->rfc);

        return view('livewire.change-requests.review-by-change-manager', [
            'eligibleMembers' => $this->eligibleMembers(),
        ])->title('Tinjauan Change Manager - '.$this->rfc->displayIdentifier());
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $approved = DecisionStatus::APPROVED->value;

        return [
            'risk_level' => ['required', 'string', Rule::in(['Rendah', 'Sedang', 'Tinggi', 'Kritis'])],
            'impact_level' => ['required', 'string', Rule::in(['Rendah', 'Sedang', 'Tinggi', 'Kritis'])],
            'urgency_level' => ['required', 'string', Rule::in(['Rendah', 'Normal', 'Tinggi', 'Emergency'])],
            'classification' => ['required', 'string', Rule::in(['Standard Change', 'Normal Change', 'Emergency Change'])],
            'analysis_notes' => ['required', 'string', 'max:1000'],
            'decisionStatus' => ['required', Rule::in(DecisionStatus::formValues())],
            'notes' => ['required', 'string', 'max:1000'],
            'selectedMemberId' => ['required_if:decisionStatus,'.$approved, 'nullable', 'integer'],
            'instruction' => ['required_if:decisionStatus,'.$approved, 'nullable', 'string', 'min:10', 'max:1000'],
            'confirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'risk_level.required' => 'Tingkat risiko wajib dipilih.',
            'impact_level.required' => 'Tingkat dampak wajib dipilih.',
            'urgency_level.required' => 'Urgensi perubahan wajib dipilih.',
            'classification.required' => 'Klasifikasi perubahan wajib dipilih.',
            'analysis_notes.required' => 'Catatan analisis risiko wajib diisi.',
            'analysis_notes.max' => 'Catatan analisis risiko maksimal 1.000 karakter.',
            'decisionStatus.required' => 'Keputusan Change Manager wajib dipilih.',
            'decisionStatus.in' => 'Pilihan keputusan tidak valid untuk alur baru.',
            'notes.required' => 'Catatan keputusan wajib diisi.',
            'notes.max' => 'Catatan keputusan maksimal 1.000 karakter.',
            'selectedMemberId.required_if' => 'Pilih satu anggota Tim Rilis sebagai penerima disposisi.',
            'selectedMemberId.integer' => 'Pilihan penerima disposisi tidak valid.',
            'instruction.required_if' => 'Instruksi disposisi wajib diisi.',
            'instruction.min' => 'Instruksi disposisi minimal 10 karakter.',
            'instruction.max' => 'Instruksi disposisi maksimal 1.000 karakter.',
            'confirmed.accepted' => 'Centang pernyataan konfirmasi sebelum menyimpan tinjauan.',
        ];
    }

    /**
     * @return Collection<int, ReleaseTeamMember>
     */
    private function eligibleMembers(): Collection
    {
        return ReleaseTeamMember::query()
            ->with(['user.roles'])
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->whereHas('user', function (Builder $query): void {
                $query->where('is_active', true)
                    ->whereHas('roles', fn (Builder $role): Builder => $role->where('name', 'Tim Rilis'));
            })
            ->orderBy('name')
            ->get();
    }

    private function normalizeValue(?string $value, string $default): string
    {
        if (! $value) {
            return $default;
        }

        $lower = strtolower(trim($value));

        return $this->valueMap[$lower] ?? $value;
    }

    private function classificationForChangeType(?string $changeType): string
    {
        return match ((string) $changeType) {
            'standard' => 'Standard Change',
            'emergency' => 'Emergency Change',
            default => 'Normal Change',
        };
    }
}
