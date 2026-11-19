<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseTaskRiskAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'release_task_id',
        'submitted_by_id',
        'activity_plan',
        'change_plan',
        'rollback_mechanism',
    ];

    public function releaseTask(): BelongsTo
    {
        return $this->belongsTo(ReleaseTask::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }
}
