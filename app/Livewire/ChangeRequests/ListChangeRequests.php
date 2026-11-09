<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\RfcStatus;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ListChangeRequests extends Component
{
    use WithPagination;

    public ?ChangeRequest $selectedRfc = null;

    public string $activeTab = 'informasi';

    public string $search = '';

    public string $statusFilter = '';

    public string $categoryFilter = '';

    public string $applicationFilter = '';

    public string $opdFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $statusMessage = '';

    public string $queueFilter = '';

    #[On('rfc-deleted')]
    public function handleRfcDeleted(string $message = ''): void
    {
        $this->statusMessage = $message !== ''
            ? $message
            : 'RFC berhasil dihapus dan dipindahkan ke Riwayat Penghapusan.';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->queueFilter = '';
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedApplicationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedOpdFilter(): void
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
        $this->reset(
            'search',
            'statusFilter',
            'categoryFilter',
            'applicationFilter',
            'opdFilter',
            'dateFrom',
            'dateTo',
        );

        $this->resetPage();
    }

    /**
     * Unduh daftar RFC mengikuti filter yang sedang aktif.
     */
    public function export(string $format): void
    {
        $this->authorize('exportList', ChangeRequest::class);

        abort_unless(in_array($format, ['xlsx', 'csv'], true), 422);

        $this->redirect(route('change-requests.export', array_filter([
            'format' => $format,
            'basis' => 'created',
            'q' => $this->search,
            'status' => $this->statusFilter,
            'change_type' => $this->categoryFilter,
            'application_id' => $this->applicationFilter,
            'opd_id' => $this->opdFilter,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ], fn (mixed $value) => filled($value))), navigate: false);
    }

    public function mount(?ChangeRequest $rfc = null): void
    {
        $this->authorize('viewAny', ChangeRequest::class);

        $this->search = (string) request()->query('q', '');

        if (request()->query('queue') === 'incoming'
            && (Auth::user()?->can('rfc.review_completeness') ?? false)) {
            $this->queueFilter = 'incoming';
        }

        if ($rfc && $rfc->exists) {
            $this->authorize('view', $rfc);
            $this->selectedRfc = $rfc->load([
                'submittedBy',
                'application',
                'riskAssessment',
                'decisions',
                'releaseTasks',
                'evidences',
                'comments.user',
            ]);
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        $this->authorize('viewAny', ChangeRequest::class);

        /** @var User $user */
        $user = Auth::user();

        // Jika rute mengakses detail RFC ($selectedRfc terisi)
        if ($this->selectedRfc) {
            return view('livewire.change-requests.show-change-request', [
                'rfc' => $this->selectedRfc,
            ]);
        }

        // Opsi filter dipakai oleh seluruh tampilan peran.
        $filterOptions = [
            'applications' => Application::orderBy('name')->get(['id', 'name']),
            'opds' => Opd::orderBy('name')->get(['id', 'name']),
            'statuses' => collect(RfcStatus::cases())->mapWithKeys(
                fn (RfcStatus $status) => [$status->value => $status->label()]
            ),
            'changeTypes' => [
                'standard' => 'Standard',
                'normal' => 'Normal',
                'emergency' => 'Darurat (Emergency)',
            ],
        ];

        // 1. Tampilan staf: seluruh RFC hanya untuk pengguna dengan izin view_all.
        if ($user->can('rfc.view_all')) {
            // Tampilan Dashboard Admin / Frontdesk (prioritas Admin murni)
            if ($user->hasRole('Admin') && ! $user->hasRole('Change Manager')) {
                $stats = [
                    'incoming' => ChangeRequest::whereIn('status', [
                        RfcStatus::SUBMITTED->value,
                        RfcStatus::RETURNED_TO_ADMIN->value,
                    ])->count(),
                    'needs_revision' => ChangeRequest::whereIn('status', RfcStatus::valuesForGroup('needs_revision'))->count(),
                    'forwarded' => ChangeRequest::where(function (Builder $q): void {
                        $q->whereIn('status', [RfcStatus::TICKET_CREATED->value])
                            ->orWhere(fn (Builder $approved) => $approved->inStatusGroup('approved'));
                    })->count(),
                    'rejected' => ChangeRequest::inStatusGroup('rejected')->count(),
                ];

                $rfcs = $this->orderFilteredRfcs($this->applyFilters(ChangeRequest::query()->with([
                    'riskAssessment',
                    'application',
                    'implementation',
                    'submittedBy.opd',
                ])))->paginate(10);

                return view('livewire.change-requests.list-change-requests', [
                    'roleView' => 'admin',
                    'stats' => $stats,
                    'rfcs' => $rfcs,
                    'notifications' => $user->unreadNotifications()->take(5)->get(),
                ] + $filterOptions);
            }

            // Tampilan Dashboard Change Manager / Super Admin
            $stats = [
                'total' => ChangeRequest::count(),
                'pending_approval' => ChangeRequest::whereIn('status', [
                    RfcStatus::TICKET_CREATED->value,
                ])->count(),
                'approved' => ChangeRequest::inStatusGroup('approved')->count(),
                'rejected' => ChangeRequest::inStatusGroup('rejected')->count(),
                'emergency' => ChangeRequest::where('change_type', 'emergency')->count(),
            ];

            $rfcs = $this->orderFilteredRfcs($this->applyFilters(ChangeRequest::query()->with([
                'riskAssessment',
                'application',
                'implementation',
                'submittedBy.opd',
            ])))->paginate(10);

            return view('livewire.change-requests.list-change-requests', [
                'roleView' => 'change_manager',
                'stats' => $stats,
                'rfcs' => $rfcs,
                'notifications' => $user->unreadNotifications()->take(5)->get(),
            ] + $filterOptions);
        }

        // 2. Tampilan Tim Rilis: hanya RFC yang ditugaskan ke akunnya.
        if ($user->can('rfc.view_assigned') && $user->isReleaseTeam()) {
            $baseQuery = ChangeRequest::query()->assignedToReleaseTeamMember($user);

            $stats = [
                'assigned' => (clone $baseQuery)->count(),
                'in_progress' => (clone $baseQuery)->where('status', RfcStatus::IN_PROGRESS->value)->count(),
                'waiting_evaluation' => (clone $baseQuery)->whereIn('status', [
                    RfcStatus::IMPLEMENTED->value,
                    RfcStatus::FAILED->value,
                ])->count(),
                'closed' => (clone $baseQuery)->where('status', RfcStatus::CLOSED->value)->count(),
            ];

            $rfcs = $this->orderFilteredRfcs($this->applyFilters((clone $baseQuery)->with([
                'application',
                'submittedBy.opd',
                'implementation',
                'releaseTasks',
            ]), includeTicketNumber: false, includeOpdFilter: false))->paginate(10);

            return view('livewire.change-requests.list-change-requests', [
                'roleView' => 'release_team',
                'stats' => $stats,
                'rfcs' => $rfcs,
                'notifications' => $user->unreadNotifications()->take(5)->get(),
            ] + $filterOptions);
        }

        // 3. Tampilan Pemohon: hanya RFC milik pengguna dengan izin view_own.
        $baseQuery = ChangeRequest::query()->where('submitted_by_id', $user->id);

        $stats = [
            'my_rfc' => (clone $baseQuery)->count(),
            'in_process' => (clone $baseQuery)->whereIn('status', RfcStatus::valuesForGroup('in_process'))->count(),
            'needs_revision' => (clone $baseQuery)->whereIn('status', RfcStatus::valuesForGroup('needs_revision'))->count(),
            'approved' => (clone $baseQuery)->inStatusGroup('approved')->count(),
            'rejected' => (clone $baseQuery)->inStatusGroup('rejected')->count(),
        ];

        $rfcs = $this->orderFilteredRfcs($this->applyFilters((clone $baseQuery)->with([
            'riskAssessment',
            'application',
            'implementation',
            'submittedBy.opd',
        ])->withExists('satisfaction')))->paginate(10);

        return view('livewire.change-requests.list-change-requests', [
            'roleView' => 'pemohon',
            'stats' => $stats,
            'rfcs' => $rfcs,
            'notifications' => $user->unreadNotifications()->take(5)->get(),
        ] + $filterOptions);
    }

    private function orderFilteredRfcs(Builder $query): Builder
    {
        return $this->queueFilter === 'incoming'
            ? $query->oldest()
            : $query->latest();
    }

    private function applyFilters(Builder $query, bool $includeTicketNumber = true, bool $includeOpdFilter = true): Builder
    {
        return $query
            ->when($this->queueFilter === 'incoming', fn (Builder $q) => $q->whereIn('status', [
                RfcStatus::SUBMITTED->value,
                RfcStatus::RETURNED_TO_ADMIN->value,
            ]))
            ->when($this->search !== '', function (Builder $query) use ($includeTicketNumber): void {
                $draftId = ChangeRequest::draftIdFromSearch($this->search);

                $query->where(function (Builder $sub) use ($draftId, $includeTicketNumber): void {
                    $sub->where('title', 'like', "%{$this->search}%")
                        ->orWhere('rfc_number', 'like', "%{$this->search}%");

                    if ($includeTicketNumber) {
                        $sub->orWhere('ticket_number', 'like', "%{$this->search}%");
                    }

                    if ($draftId !== null) {
                        $sub->orWhere(fn (Builder $draft) => $draft
                            ->whereKey($draftId)
                            ->where('status', RfcStatus::DRAFT->value));
                    }
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->categoryFilter !== '', fn (Builder $q) => $q->where('change_type', $this->categoryFilter))
            ->when($this->applicationFilter !== '', fn (Builder $q) => $q->where('application_id', $this->applicationFilter))
            ->when($includeOpdFilter && $this->opdFilter !== '', fn (Builder $q) => $q->whereHas(
                'submittedBy',
                fn (Builder $sub) => $sub->where('opd_id', $this->opdFilter)
            ))
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('created_at', '<=', $this->dateTo));
    }
}
