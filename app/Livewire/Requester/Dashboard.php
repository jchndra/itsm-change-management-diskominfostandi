<?php

namespace App\Livewire\Requester;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcHistoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    public string $statusMessage = '';

    #[On('rfc-deleted')]
    public function handleRfcDeleted(string $message = ''): void
    {
        $this->statusMessage = $message !== ''
            ? $message
            : 'RFC berhasil dihapus dan dipindahkan ke Riwayat Penghapusan.';
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        // Keamanan: Hanya ambil RFC yang dibuat oleh user ini
        $baseQuery = ChangeRequest::where('submitted_by_id', $user->id);

        // Menghitung statistik berdasarkan grup status yang sama dengan label tabel.
        $stats = [
            'draft' => (clone $baseQuery)->whereIn('status', RfcStatus::valuesForGroup('draft'))->count(),
            'in_process' => (clone $baseQuery)->whereIn('status', RfcStatus::valuesForGroup('in_process'))->count(),
            'needs_revision' => (clone $baseQuery)->whereIn('status', RfcStatus::valuesForGroup('needs_revision'))->count(),
            'approved' => (clone $baseQuery)->whereIn('status', RfcStatus::valuesForGroup('approved'))->count(),
            'rejected' => (clone $baseQuery)->whereIn('status', RfcStatus::valuesForGroup('rejected'))->count(),
        ];

        // 5 RFC terbaru untuk tabel
        $recentRfcs = (clone $baseQuery)
            ->with('application')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        // RFC terakhir yang masih berjalan; status terminal tidak boleh tampil di widget.
        $latestActiveRfc = (clone $baseQuery)
            ->whereNotIn('status', [
                RfcStatus::CLOSED->value,
                RfcStatus::REJECTED->value,
                RfcStatus::CANCELLED->value,
                RfcStatus::FAILED->value,
            ])
            ->latest('updated_at')
            ->first();

        $timeline = $latestActiveRfc
            ? app(RfcHistoryService::class)->dashboardTimeline($latestActiveRfc, false)
            : [];

        // Notifikasi terbaru (sudah dibaca maupun belum)
        $notifications = $user->notifications()->take(4)->get();

        return view('livewire.requester.dashboard', [
            'stats' => $stats,
            'recentRfcs' => $recentRfcs,
            'latestActiveRfc' => $latestActiveRfc,
            'timeline' => $timeline,
            'notifications' => $notifications,
        ])->title('Dashboard Pemohon');
    }
}
