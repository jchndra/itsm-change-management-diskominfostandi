<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RfcSatisfactionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ChangeRequest $rfc,
        public int $rating,
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

        return [
            'rfc_id' => $this->rfc->id,
            'rfc_number' => $identifier,
            'title' => $this->rfc->title,
            'event' => 'satisfaction_submitted',
            'rating' => $this->rating,
            'message' => "Pemohon memberi penilaian {$this->rating}/5 untuk RFC {$identifier}",
            'url' => route('change-requests.show', ['rfc' => $this->rfc->id]),
        ];
    }
}
