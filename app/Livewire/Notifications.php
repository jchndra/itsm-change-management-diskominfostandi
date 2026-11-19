<?php

namespace App\Livewire;

use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Notifications extends Component
{
    use WithPagination;

    public const DISCUSSION_EVENT = 'comment_added';

    public string $discussionFilter = 'all';

    public string $otherFilter = 'all';

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
            $url = route('change-requests.show', ['rfc' => $rfcId]);

            if (data_get($notification->data, 'event') === self::DISCUSSION_EVENT) {
                $url .= '#diskusi';
            }

            $this->redirect($url, navigate: true);

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

    public function markDiscussionAsRead(): void
    {
        $this->markSectionAsRead('discussion');
    }

    public function markOtherAsRead(): void
    {
        $this->markSectionAsRead('other');
    }

    public function setDiscussionFilter(string $filter): void
    {
        $this->discussionFilter = $this->normalizeFilter($filter);
        $this->resetPage('diskusiPage');
    }

    public function setOtherFilter(string $filter): void
    {
        $this->otherFilter = $this->normalizeFilter($filter);
        $this->resetPage('lainnyaPage');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $discussionQuery = $this->applyFilter($this->discussionQuery($user), $this->discussionFilter);
        $otherQuery = $this->applyFilter($this->otherQuery($user), $this->otherFilter);

        return view('livewire.notifications.index', [
            'discussionNotifications' => $discussionQuery->paginate(10, ['*'], 'diskusiPage'),
            'otherNotifications' => $otherQuery->paginate(10, ['*'], 'lainnyaPage'),
            'discussionTotalCount' => $this->discussionQuery($user)->count(),
            'otherTotalCount' => $this->otherQuery($user)->count(),
            'discussionUnreadCount' => $this->discussionQuery($user)->whereNull('read_at')->count(),
            'otherUnreadCount' => $this->otherQuery($user)->whereNull('read_at')->count(),
            'unreadCount' => $user->unreadNotifications()->count(),
            'totalCount' => $user->notifications()->count(),
        ])->title('Notifikasi');
    }

    /**
     * @return MorphMany<DatabaseNotification, User>
     */
    private function discussionQuery(User $user): MorphMany
    {
        return $user->notifications()->where('data->event', self::DISCUSSION_EVENT);
    }

    /**
     * @return MorphMany<DatabaseNotification, User>
     */
    private function otherQuery(User $user): MorphMany
    {
        return $user->notifications()->where(function (Builder $query): void {
            $query->whereNull('data->event')
                ->orWhere('data->event', '!=', self::DISCUSSION_EVENT);
        });
    }

    /**
     * @param  MorphMany<DatabaseNotification, User>  $query
     * @return MorphMany<DatabaseNotification, User>
     */
    private function applyFilter(MorphMany $query, string $filter): MorphMany
    {
        return match ($filter) {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            default => $query,
        };
    }

    private function markSectionAsRead(string $section): void
    {
        /** @var User $user */
        $user = Auth::user();

        $query = $section === 'discussion'
            ? $this->discussionQuery($user)
            : $this->otherQuery($user);

        $query->whereNull('read_at')->get()->markAsRead();

        $this->redirectRoute('notifications.index', navigate: true);
    }

    private function normalizeFilter(string $filter): string
    {
        return in_array($filter, ['all', 'unread', 'read'], true) ? $filter : 'all';
    }
}
