<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseTaskUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'release_task_id',
        'change_request_id',
        'user_id',
        'type',
        'note',
        'reason',
        'old_estimated_days',
        'new_estimated_days',
    ];

    protected function casts(): array
    {
        return [
            'old_estimated_days' => 'integer',
            'new_estimated_days' => 'integer',
        ];
    }

    public function releaseTask(): BelongsTo
    {
        return $this->belongsTo(ReleaseTask::class);
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
