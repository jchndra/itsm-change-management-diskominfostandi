<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RfcDispositionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ChangeRequest $rfc,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Sengaja tidak mengirim "status" agar ikon notifikasi memakai ikon
     * informasi, bukan ikon perubahan status yang sudah ada.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $identifier = $this->rfc->displayIdentifier();

        return [
            'rfc_id' => $this->rfc->id,
            'rfc_number' => $identifier,
            'title' => $this->rfc->title,
            'message' => "{$identifier} diteruskan ke Tim Rilis",
            'url' => route('change-requests.show', ['rfc' => $this->rfc->id]),
        ];
    }
}
