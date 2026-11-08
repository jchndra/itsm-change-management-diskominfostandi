<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RfcReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ChangeRequest $rfc,
        public string $kind,
        public int $days,
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
            'event' => 'reminder_'.$this->kind,
            'message' => $this->message($identifier),
            'url' => route('change-requests.show', ['rfc' => $this->rfc->id]),
            'status' => $this->rfc->status,
        ];
    }

    private function message(string $identifier): string
    {
        return match ($this->kind) {
            'pending_review' => "RFC {$identifier} sudah {$this->days} hari menunggu review Anda",
            'approved_unassigned' => "RFC {$identifier} sudah {$this->days} hari disetujui tanpa disposisi Tim Rilis",
            'task_not_started' => "Tugas RFC {$identifier} belum dimulai selama {$this->days} hari",
            'task_overdue' => "Tugas RFC {$identifier} melewati estimasi {$this->days} hari",
            'waiting_evaluation' => "RFC {$identifier} sudah {$this->days} hari menunggu evaluasi Anda",
            default => "RFC {$identifier} memerlukan tindak lanjut",
        };
    }
}
