<?php

namespace App\Models;

use App\Enums\ServiceCategory;
use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rfc_number',
        'ticket_number',
        'title',
        'application_id',
        'submitted_by_id',
        'created_by_id',
        'is_physical_letter',
        'physical_letter_number',
        'reason_for_change',
        'description',
        'impact_description',
        'status',
        'change_type',
        'emergency_unconfirmed',
        'priority',
        'category',
        'admin_note',
        'ticket_created_at',
        'ticket_created_by_id',
        'rejection_note',
        'target_implementation_date',
        'closed_at',
        'deleted_by_id',
        'deletion_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_physical_letter' => 'boolean',
            'emergency_unconfirmed' => 'boolean',
            'priority' => TicketPriority::class,
            'category' => ServiceCategory::class,
            'ticket_created_at' => 'datetime',
            'target_implementation_date' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    public function riskAssessment(): HasOne
    {
        return $this->hasOne(RiskAssessment::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }

    public function authorizationDocuments(): HasMany
    {
        return $this->hasMany(AuthorizationDocument::class);
    }

    public function releaseTasks(): HasMany
    {
        return $this->hasMany(ReleaseTask::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChangeRequestAttachment::class);
    }
}
