<?php

namespace App\Notifications;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChangeManagerTicketNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ChangeRequest $rfc,
        public string $ticketNumber,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'rfc_id' => $this->rfc->id,
            'rfc_number' => $this->rfc->displayIdentifier(),
            'ticket_number' => $this->ticketNumber,
            'title' => $this->rfc->title,
            'status' => RfcStatus::TICKET_CREATED->value,
            'message' => "Tiket {$this->ticketNumber} ({$this->rfc->displayIdentifier()}) menunggu review Anda",
        ];
    }
}
