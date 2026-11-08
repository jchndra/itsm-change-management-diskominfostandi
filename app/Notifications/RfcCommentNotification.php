<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RfcCommentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ChangeRequest $rfc,
        public User $author,
        public bool $isInternal = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $identifier = $this->rfc->displayIdentifier();

        $message = $this->isInternal
            ? "{$this->author->name} menambahkan catatan internal pada RFC {$identifier}"
            : "{$this->author->name} menambahkan komentar pada RFC {$identifier}";

        return [
            'rfc_id' => $this->rfc->id,
            'rfc_number' => $identifier,
            'title' => $this->rfc->title,
            'event' => 'comment_added',
            'message' => $message,
            'url' => route('change-requests.show', ['rfc' => $this->rfc->id]).'#diskusi',
            'status' => $this->rfc->status,
        ];
    }
}
