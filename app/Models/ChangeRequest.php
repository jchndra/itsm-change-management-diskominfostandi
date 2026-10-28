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
use Illuminate\Support\Carbon;
use RuntimeException;

class ChangeRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rfc_number',
        'submission_token',
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

    protected static function booted(): void
    {
        static::updating(function (self $rfc): void {
            if (! $rfc->isDirty('rfc_number')) {
                return;
            }

            $originalNumber = $rfc->getOriginal('rfc_number');

            if (filled($originalNumber)) {
                throw new RuntimeException('Nomor RFC resmi bersifat permanen dan tidak dapat diubah.');
            }

            if (blank($rfc->rfc_number)) {
                return;
            }

            $status = RfcStatus::fromValue($rfc->status);

            if ($status === null || $status === RfcStatus::DRAFT) {
                throw new RuntimeException('Nomor RFC hanya dapat diberikan saat RFC diajukan.');
            }
        });
    }

    /**
     * Identitas yang ditampilkan ke pengguna. Draft tidak pernah memiliki
     * nomor resmi, sehingga selalu ditampilkan sebagai "Draft #<id>".
     */
    public function displayIdentifier(): string
    {
        return self::formatIdentifier($this->rfc_number, $this->id, $this->status);
    }

    /**
     * Versi statis untuk baris query yang tidak dihidrasi sebagai model.
     */
    public static function formatIdentifier(?string $rfcNumber, int|string|null $id, ?string $status): string
    {
        if (filled($rfcNumber) && RfcStatus::fromValue($status) !== RfcStatus::DRAFT) {
            return (string) $rfcNumber;
        }

        return 'Draft #'.$id;
    }

    /**
     * Ekstrak ID dari pencarian berbentuk "Draft #<id>" agar pengguna dapat
     * mencari draft menggunakan identitas yang tampil di antarmuka.
     */
    public static function draftIdFromSearch(string $search): ?int
    {
        if (preg_match('/^\s*draft\s*#?\s*(\d+)\s*$/i', $search, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
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
     * Indikator sisa hari menuju target pelaksanaan dalam zona waktu Jakarta.
     * Tidak ditampilkan untuk draft maupun status akhir.
     *
     * @return array{label: string, tone: string, classes: string, days: int, state: string}|null
     */
    public function targetDateIndicator(): ?array
    {
        $status = RfcStatus::fromValue($this->status);

        if ($status === null
            || in_array($status, [RfcStatus::DRAFT, RfcStatus::CLOSED, RfcStatus::REJECTED, RfcStatus::CANCELLED], true)
            || $this->target_implementation_date === null) {
            return null;
        }

        $targetDate = $this->target_implementation_date->copy()
            ->timezone('Asia/Jakarta')
            ->startOfDay();
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $days = (int) floor(($targetDate->getTimestamp() - $today->getTimestamp()) / 86400);

        if ($days < 0) {
            $absoluteDays = abs($days);

            return [
                'label' => 'Terlambat '.$absoluteDays.' hari',
                'tone' => 'danger',
                'classes' => 'border-red-200 bg-red-100 text-red-800',
                'days' => $days,
                'state' => 'overdue',
            ];
        }

        if ($days <= 3) {
            return [
                'label' => $days === 0 ? 'Hari ini' : $days.' hari lagi',
                'tone' => 'warning',
                'classes' => 'border-yellow-200 bg-yellow-100 text-yellow-800',
                'days' => $days,
                'state' => 'due-soon',
            ];
        }

        return [
            'label' => $days.' hari lagi',
            'tone' => 'neutral',
            'classes' => 'border-slate-200 bg-slate-100 text-slate-700',
            'days' => $days,
            'state' => 'upcoming',
        ];
    }

    /**
     * Usia RFC dalam hari penuh sejak diajukan, hanya selama belum berstatus akhir.
     */
    public function runningDays(): ?int
    {
        $status = RfcStatus::fromValue($this->status);

        if ($status === null
            || in_array($status, [RfcStatus::CLOSED, RfcStatus::REJECTED, RfcStatus::CANCELLED], true)
            || $this->created_at === null) {
            return null;
        }

        $createdDate = $this->created_at->copy()
            ->timezone('Asia/Jakarta')
            ->startOfDay();
        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        return max(0, (int) floor(($today->getTimestamp() - $createdDate->getTimestamp()) / 86400));
    }

    /**
     * Posisi RFC saat ini sebagai peran dan tahap internal. Status akhir tidak
     * memiliki baris posisi. Tahap hanya ditampilkan kepada staf.
     *
     * @return array{role: string, stage: ?string, legacy: bool}|null
     */
    public function currentPosition(): ?array
    {
        $status = RfcStatus::fromValue($this->status);

        if ($status === null
            || in_array($status, [RfcStatus::CLOSED, RfcStatus::REJECTED, RfcStatus::CANCELLED], true)) {
            return null;
        }

        return match ($status) {
            RfcStatus::DRAFT => [
                'role' => 'Pemohon',
                'stage' => 'belum diajukan',
                'legacy' => false,
            ],
            RfcStatus::NEEDS_REVISION => [
                'role' => 'Pemohon',
                'stage' => 'revisi',
                'legacy' => false,
            ],
            RfcStatus::SUBMITTED, RfcStatus::RETURNED_TO_ADMIN => [
                'role' => 'Admin',
                'stage' => 'pemeriksaan',
                'legacy' => true,
            ],
            RfcStatus::TICKET_CREATED => [
                'role' => 'Change Manager',
                'stage' => $this->riskAssessment ? 'keputusan' : 'analisis risiko',
                'legacy' => false,
            ],
            RfcStatus::APPROVED => ($this->relationLoaded('releaseTasks')
                ? $this->releaseTasks->isNotEmpty()
                : $this->releaseTasks()->exists())
                ? [
                    'role' => 'Tim Rilis',
                    'stage' => 'pelaksanaan',
                    'legacy' => false,
                ]
                : [
                    'role' => 'Change Manager',
                    'stage' => 'disposisi',
                    'legacy' => false,
                ],
            RfcStatus::IN_PROGRESS => [
                'role' => 'Tim Rilis',
                'stage' => 'pelaksanaan',
                'legacy' => false,
            ],
            RfcStatus::IMPLEMENTED, RfcStatus::FAILED => [
                'role' => 'Change Manager',
                'stage' => $this->implementation?->evaluated_at === null ? 'evaluasi' : 'penutupan',
                'legacy' => false,
            ],
        };
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

    public function scopeAssignedToReleaseTeamMember(Builder $query, User $user): Builder
    {
        return $query->whereHas('releaseTasks.releaseTeamMember', function (Builder $member) use ($user): void {
            $member->where('user_id', $user->id)
                ->where('is_active', true);
        });
    }

    public function releaseTaskFor(User $user): ?ReleaseTask
    {
        return $this->releaseTasks()
            ->whereHas('releaseTeamMember', function (Builder $member) use ($user): void {
                $member->where('user_id', $user->id)
                    ->where('is_active', true);
            })
            ->first();
    }

    public function hasReleaseTaskFor(User $user): bool
    {
        return $this->releaseTasks()
            ->whereHas('releaseTeamMember', function (Builder $member) use ($user): void {
                $member->where('user_id', $user->id)
                    ->where('is_active', true);
            })
            ->exists();
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

    public function approvalLetters(): HasMany
    {
        return $this->hasMany(ChangeRequestAttachment::class)
            ->where('document_type', ChangeRequestAttachment::TYPE_APPROVAL_LETTER)
            ->latest('id');
    }

    public function latestApprovalLetter(): HasOne
    {
        return $this->hasOne(ChangeRequestAttachment::class)
            ->where('document_type', ChangeRequestAttachment::TYPE_APPROVAL_LETTER)
            ->latestOfMany();
    }

    public function supportingAttachments(): HasMany
    {
        return $this->hasMany(ChangeRequestAttachment::class)
            ->where('document_type', ChangeRequestAttachment::TYPE_SUPPORTING)
            ->oldest('id');
    }
}
