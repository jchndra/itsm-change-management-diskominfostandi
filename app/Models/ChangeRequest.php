<?php

namespace App\Models;

use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Enums\ServiceCategory;
use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Builder;
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
        'closed_by_id',
        'closure_notes',
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

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    public function implementation(): HasOne
    {
        return $this->hasOne(ChangeRequestImplementation::class);
    }

    /**
     * Hasil akhir implementasi RFC ini: successful, unsuccessful, atau null
     * bila data implementasi tidak tersedia (mis. RFC lama yang ditutup
     * sebelum tabel implementasi ada).
     */
    public function implementationOutcome(): ?string
    {
        return match ($this->implementation?->implementation_result) {
            ImplementationResult::SUCCESS->value => 'successful',
            ImplementationResult::FAILED->value,
            ImplementationResult::ROLLED_BACK->value => 'unsuccessful',
            default => null,
        };
    }

    /**
     * Label status sistem yang membedakan hasil akhir RFC: closed gagal atau
     * rollback tidak boleh terbaca sebagai berhasil atau selesai.
     */
    public function displayStatusLabel(): string
    {
        $status = RfcStatus::fromValue($this->status);

        if ($status === null) {
            return RfcStatus::labelFor($this->status);
        }

        if ($status === RfcStatus::CLOSED) {
            return match ($this->implementationOutcome()) {
                'successful' => 'Ditutup — Berhasil',
                'unsuccessful' => 'Ditutup — Tidak Berhasil',
                default => 'Ditutup',
            };
        }

        return $status->label();
    }

    public function displayStatusTone(): string
    {
        $status = RfcStatus::fromValue($this->status);

        if ($status === null) {
            return RfcStatus::badgeClassesFor($this->status);
        }

        if ($status === RfcStatus::CLOSED) {
            return match ($this->implementationOutcome()) {
                'successful' => 'bg-emerald-100 text-emerald-700',
                'unsuccessful' => 'bg-rose-100 text-rose-700',
                default => 'bg-slate-200 text-slate-700',
            };
        }

        return $status->badgeClasses();
    }

    /**
     * Null-safe terhadap RFC tanpa catatan implementasi; RFC berstatus failed
     * selalu dianggap tidak berhasil meskipun datanya belum lengkap.
     */
    public function isImplementationUnsuccessful(): bool
    {
        if (RfcStatus::fromValue($this->status) === RfcStatus::FAILED) {
            return true;
        }

        return $this->implementationOutcome() === 'unsuccessful';
    }

    /**
     * Filter berdasarkan kelompok status yang memperhitungkan hasil akhir:
     * closed berhasil (atau RFC lama tanpa data implementasi) masuk grup
     * approved, sedangkan closed gagal/rollback masuk grup rejected.
     */
    public function scopeInStatusGroup(Builder $query, string $group): Builder
    {
        return match ($group) {
            'approved' => $query->where(function (Builder $q): void {
                $q->whereIn('status', [RfcStatus::APPROVED->value, RfcStatus::IMPLEMENTED->value])
                    ->orWhere(function (Builder $closed): void {
                        $closed->where('status', RfcStatus::CLOSED->value)
                            ->where(function (Builder $impl): void {
                                $impl->whereDoesntHave('implementation')
                                    ->orWhereHas('implementation', fn (Builder $result): Builder => $result->where(
                                        'implementation_result',
                                        ImplementationResult::SUCCESS->value,
                                    ));
                            });
                    });
            }),
            'rejected' => $query->where(function (Builder $q): void {
                $q->whereIn('status', [RfcStatus::REJECTED->value, RfcStatus::FAILED->value])
                    ->orWhere(function (Builder $closed): void {
                        $closed->where('status', RfcStatus::CLOSED->value)->whereHas(
                            'implementation',
                            fn (Builder $result): Builder => $result->whereIn('implementation_result', [
                                ImplementationResult::FAILED->value,
                                ImplementationResult::ROLLED_BACK->value,
                            ])
                        );
                    });
            }),
            default => $query->whereIn('status', RfcStatus::valuesForGroup($group)),
        };
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
