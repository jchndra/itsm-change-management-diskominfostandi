<?php

namespace App\Notifications;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RfcStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ChangeRequest $rfc,
        public RfcStatus $newStatus,
        public ?string $note = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'rfc_id' => $this->rfc->id,
            'rfc_number' => $this->rfc->rfc_number,
            'title' => $this->rfc->title,
            'status' => $this->newStatus->value,
            'message' => $this->message(),
        ];
    }

    private function message(): string
    {
        $number = $this->rfc->rfc_number;

        return match ($this->newStatus) {
            RfcStatus::SUBMITTED => "RFC {$number} berhasil diajukan",
            RfcStatus::NEEDS_REVISION => $this->withNote("RFC {$number} perlu direvisi"),
            RfcStatus::TICKET_CREATED => "RFC {$number} sedang ditinjau",
            RfcStatus::APPROVED => "RFC {$number} disetujui",
            RfcStatus::REJECTED => $this->withNote("RFC {$number} ditolak"),
            RfcStatus::IN_PROGRESS => "RFC {$number} sedang dikerjakan",
            RfcStatus::IMPLEMENTED, RfcStatus::CLOSED => "RFC {$number} telah selesai",
            RfcStatus::FAILED => "RFC {$number} gagal dilaksanakan",
            RfcStatus::DRAFT, RfcStatus::CANCELLED => "RFC {$number} diperbarui",
        };
    }

    private function withNote(string $message): string
    {
        $note = $this->note ?? $this->rfc->rejection_note;

        return filled($note) ? "{$message}: ".trim((string) $note) : $message;
    }
}
