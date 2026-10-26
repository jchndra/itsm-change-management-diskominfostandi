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
            'outcome' => $this->outcome(),
            'message' => $this->message(),
        ];
    }

    /**
     * Hasil akhir untuk ikon notifikasi: implemented tinggal menunggu
     * evaluasi, sedangkan closed mengikuti hasil implementasi tercatat.
     */
    private function outcome(): ?string
    {
        return match ($this->newStatus) {
            RfcStatus::IMPLEMENTED => 'successful',
            RfcStatus::FAILED => 'unsuccessful',
            RfcStatus::CLOSED => $this->rfc->implementationOutcome(),
            default => null,
        };
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
            RfcStatus::IMPLEMENTED => "RFC {$number} selesai diimplementasikan, menunggu evaluasi",
            RfcStatus::FAILED => "RFC {$number} gagal atau rollback dilaksanakan, menunggu evaluasi",
            RfcStatus::CLOSED => "RFC {$number} ".match ($this->outcome()) {
                'successful' => 'ditutup — berhasil',
                'unsuccessful' => 'ditutup — tidak berhasil',
                default => 'ditutup',
            },
            RfcStatus::DRAFT, RfcStatus::CANCELLED => "RFC {$number} diperbarui",
        };
    }

    private function withNote(string $message): string
    {
        $note = $this->note ?? $this->rfc->rejection_note;

        return filled($note) ? "{$message}: ".trim((string) $note) : $message;
    }
}
