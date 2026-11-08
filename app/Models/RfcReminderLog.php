<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfcReminderLog extends Model
{
    protected $fillable = [
        'change_request_id',
        'kind',
        'since_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'since_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }
}
