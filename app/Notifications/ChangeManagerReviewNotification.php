<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChangeManagerReviewNotification extends Notification
{
    use Queueable;

    public function __construct(public ChangeRequest $rfc) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'rfc_id' => $this->rfc->id,
            'rfc_number' => $this->rfc->displayIdentifier(),
            'title' => $this->rfc->title,
            'message' => $this->rfc->displayIdentifier().' menunggu pemeriksaan Anda',
        ];
    }
}
