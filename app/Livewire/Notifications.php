<?php

namespace App\Livewire;

use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Notifications extends Component
{
    use WithPagination;

    public string $filter = 'all';

    public function mount(): void
    {
        $notificationId = request()->query('open');

        if (! is_string($notificationId) || $notificationId === '') {
            return;
        }

        // Membuka notifikasi dari lonceng/dashboard: tandai dibaca lalu arahkan
        // ke RFC terkait. Kepemilikan diverifikasi di markAsRead().
        $this->markAsRead($notificationId);
    }

    public function markAsRead(string $id): void
    {
        /** @var User $user */
        $user = Auth::user();

        $notification = $user->notifications()->whereKey($id)->first();

        abort_if(! $notification, 403);

        $notification->markAsRead();

        $rfcId = data_get($notification->data, 'rfc_id');

        if ($rfcId && ChangeRequest::whereKey($rfcId)->exists()) {
            $this->redirectRoute('change-requests.show', ['rfc' => $rfcId], navigate: true);

            return;
        }

        $this->dispatch('$refresh');
    }

    public function markAllAsRead(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $user->unreadNotifications->markAsRead();

        // Muat ulang halaman agar lencana sidebar dan lonceng ikut diperbarui.
        $this->redirectRoute('notifications.index', navigate: true);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $query = match ($this->filter) {
            'unread' => $user->notifications()->whereNull('read_at'),
            'read' => $user->notifications()->whereNotNull('read_at'),
            default => $user->notifications(),
        };

        return view('livewire.notifications.index', [
            'notifications' => $query->paginate(15),
            'unreadCount' => $user->unreadNotifications()->count(),
            'totalCount' => $user->notifications()->count(),
        ])->title('Notifikasi');
    }
}
