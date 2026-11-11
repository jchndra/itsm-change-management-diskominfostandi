<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ReleaseTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'change_request_id',
        'release_team_member_id',
        'assigned_by_id',
        'instruction',
        'estimated_days',
        'planned_start_on',
        'scheduled_start',
        'scheduled_end',
        'execution_status',
        'executed_at',
        'execution_notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_days' => 'integer',
            'planned_start_on' => 'date',
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function releaseTeamMember(): BelongsTo
    {
        return $this->belongsTo(ReleaseTeamMember::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ReleaseTaskUpdate::class)->orderByDesc('created_at');
    }

    /**
     * Jendela jadwal tugas untuk deteksi bentrok.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function scheduleWindow(): ?array
    {
        if ($this->execution_status === 'in_progress') {
            if ($this->scheduled_start === null || $this->scheduled_end === null) {
                return null;
            }

            return [$this->scheduled_start->copy(), $this->scheduled_end->copy()];
        }

        if ($this->execution_status === 'pending' && $this->estimated_days !== null) {
            $start = $this->planned_start_on?->copy() ?? today();

            return [$start, $start->copy()->addDays($this->estimated_days)];
        }

        return null;
    }
}
