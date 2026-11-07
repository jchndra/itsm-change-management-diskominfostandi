<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use App\Models\ReleaseTaskUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RfcReleaseProgressNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ChangeRequest $rfc,
        public ReleaseTaskUpdate $update,
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
        $team = $this->update->releaseTask?->releaseTeamMember?->name ?? 'Tim Rilis';

        return [
            'rfc_id' => $this->rfc->id,
            'rfc_number' => $identifier,
            'title' => $this->rfc->title,
            'event' => $this->update->type,
            'message' => $this->message($identifier, $team),
            'url' => route('change-requests.show', ['rfc' => $this->rfc->id]),
        ];
    }

    private function message(string $identifier, string $team): string
    {
        return match ($this->update->type) {
            'started' => "RFC {$identifier}: pengerjaan oleh {$team} dimulai",
            'estimate_set' => "RFC {$identifier}: estimasi {$team} ditetapkan {$this->update->new_estimated_days} hari",
            'estimate_revised' => "RFC {$identifier}: estimasi {$team} direvisi {$this->update->old_estimated_days} -> {$this->update->new_estimated_days} hari. Alasan: {$this->update->reason}",
            'blocked' => "RFC {$identifier}: kendala pengerjaan dicatat oleh {$team}. {$this->update->note}",
            default => "RFC {$identifier}: progres {$team} diperbarui",
        };
    }
}
