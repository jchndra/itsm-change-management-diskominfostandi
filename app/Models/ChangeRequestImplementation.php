<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequestImplementation extends Model
{
    use HasFactory;

    protected $fillable = [
        'change_request_id',
        'recorded_by_id',
        'implementation_result',
        'started_at',
        'finished_at',
        'executor_name',
        'report_source',
        'summary',
        'test_results',
        'failure_notes',
        'follow_up_action',
        'follow_up_notes',
        'evaluation_notes',
        'evaluated_by_id',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by_id');
    }
}
