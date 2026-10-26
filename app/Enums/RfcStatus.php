<?php

namespace App\Enums;

enum RfcStatus: string
{
    public const LEGACY_COMPLETE = 'complete';

    public const LEGACY_ASSESSED = 'assessed';

    public const LEGACY_EXECUTING = 'executing';

    public const LEGACY_SUCCESSFUL = 'successful';

    public const LEGACY_ROLLED_BACK = 'rolled_back';

    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case NEEDS_REVISION = 'needs_revision';
    case TICKET_CREATED = 'ticket_created';
    case RETURNED_TO_ADMIN = 'returned_to_admin';
    case APPROVED = 'approved';
    case IN_PROGRESS = 'in_progress';
    case IMPLEMENTED = 'implemented';
    case FAILED = 'failed';
    case CLOSED = 'closed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Diajukan',
            self::NEEDS_REVISION => 'Perlu Revisi',
            self::TICKET_CREATED, self::RETURNED_TO_ADMIN => 'Sedang Ditinjau',
            self::APPROVED => 'Disetujui',
            self::IN_PROGRESS => 'Sedang Dikerjakan',
            self::IMPLEMENTED => 'Menunggu Evaluasi',
            self::FAILED => 'Gagal / Rollback',
            self::CLOSED => 'Ditutup',
            self::REJECTED => 'Ditolak',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-slate-100 text-slate-600',
            self::SUBMITTED => 'bg-blue-100 text-blue-700',
            self::NEEDS_REVISION => 'bg-amber-100 text-amber-700',
            self::TICKET_CREATED, self::RETURNED_TO_ADMIN => 'bg-indigo-100 text-indigo-700',
            self::APPROVED => 'bg-emerald-100 text-emerald-700',
            self::IN_PROGRESS => 'bg-blue-100 text-blue-700',
            self::IMPLEMENTED => 'bg-amber-100 text-amber-700',
            self::CLOSED => 'bg-slate-200 text-slate-700',
            self::FAILED => 'bg-red-700 text-white',
            self::REJECTED => 'bg-rose-100 text-rose-700',
            self::CANCELLED => 'bg-slate-100 text-slate-500',
        };
    }

    public function groupKey(): string
    {
        return match ($this) {
            self::DRAFT => 'draft',
            self::SUBMITTED, self::TICKET_CREATED, self::RETURNED_TO_ADMIN, self::IN_PROGRESS => 'in_process',
            self::NEEDS_REVISION => 'needs_revision',
            self::APPROVED, self::IMPLEMENTED, self::CLOSED => 'approved',
            self::REJECTED, self::FAILED => 'rejected',
            self::CANCELLED => 'cancelled',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT, self::NEEDS_REVISION => [self::SUBMITTED, self::CANCELLED],
            self::SUBMITTED => [self::NEEDS_REVISION, self::TICKET_CREATED, self::REJECTED, self::CANCELLED],
            self::TICKET_CREATED => [self::APPROVED, self::REJECTED, self::RETURNED_TO_ADMIN],
            self::RETURNED_TO_ADMIN => [self::TICKET_CREATED, self::NEEDS_REVISION, self::REJECTED],
            self::APPROVED => [self::IN_PROGRESS],
            self::IN_PROGRESS => [self::IMPLEMENTED, self::FAILED],
            self::IMPLEMENTED, self::FAILED => [self::CLOSED],
            self::REJECTED, self::CLOSED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Aktor yang diizinkan untuk transisi ini.
     *
     * Nilai: owner, admin, change_manager, atau null jika tidak diizinkan.
     */
    public function actorFor(self $target): ?string
    {
        return match (true) {
            in_array($this, [self::DRAFT, self::NEEDS_REVISION], true)
                && in_array($target, [self::SUBMITTED, self::CANCELLED], true) => 'owner',
            $this === self::SUBMITTED
                && in_array($target, [self::NEEDS_REVISION, self::TICKET_CREATED, self::REJECTED], true) => 'admin',
            $this === self::SUBMITTED && $target === self::CANCELLED => 'admin',
            $this === self::RETURNED_TO_ADMIN
                && in_array($target, [self::TICKET_CREATED, self::NEEDS_REVISION, self::REJECTED], true) => 'admin',
            $this === self::TICKET_CREATED
                && in_array($target, [self::APPROVED, self::REJECTED, self::RETURNED_TO_ADMIN], true) => 'change_manager',
            $this === self::APPROVED && $target === self::IN_PROGRESS => 'change_manager',
            $this === self::IN_PROGRESS
                && in_array($target, [self::IMPLEMENTED, self::FAILED], true) => 'change_manager',
            in_array($this, [self::IMPLEMENTED, self::FAILED], true)
                && $target === self::CLOSED => 'change_manager',
            default => null,
        };
    }

    public function shouldNotifyPemohon(): bool
    {
        return match ($this) {
            self::SUBMITTED,
            self::TICKET_CREATED,
            self::NEEDS_REVISION,
            self::APPROVED,
            self::REJECTED,
            self::IN_PROGRESS,
            self::IMPLEMENTED,
            self::CLOSED,
            self::FAILED => true,
            default => false,
        };
    }

    /**
     * @return array<string, array{label: string, statuses: array<int, self>}>
     */
    public static function groups(): array
    {
        return [
            'draft' => [
                'label' => 'Draft',
                'statuses' => [self::DRAFT],
            ],
            'in_process' => [
                'label' => 'Dalam Proses',
                'statuses' => [self::SUBMITTED, self::TICKET_CREATED, self::RETURNED_TO_ADMIN, self::IN_PROGRESS],
            ],
            'needs_revision' => [
                'label' => 'Perlu Revisi',
                'statuses' => [self::NEEDS_REVISION],
            ],
            'approved' => [
                'label' => 'Disetujui',
                'statuses' => [self::APPROVED, self::IMPLEMENTED, self::CLOSED],
            ],
            'rejected' => [
                'label' => 'Ditolak / Tidak Terlaksana',
                'statuses' => [self::REJECTED, self::FAILED],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function valuesForGroup(string $group): array
    {
        $definition = self::groups()[$group] ?? null;

        if (! $definition) {
            return [];
        }

        return array_map(fn (self $status) => $status->value, $definition['statuses']);
    }

    public static function groupLabel(string $group): string
    {
        return self::groups()[$group]['label'] ?? $group;
    }

    /**
     * Mendukung nilai lama (complete/assessed, dll.) agar histori tetap terbaca.
     */
    public static function fromValue(self|string|null $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value) ?? match ($value) {
            self::LEGACY_COMPLETE, self::LEGACY_ASSESSED => self::TICKET_CREATED,
            self::LEGACY_EXECUTING => self::IN_PROGRESS,
            self::LEGACY_SUCCESSFUL => self::IMPLEMENTED,
            self::LEGACY_ROLLED_BACK => self::FAILED,
            default => null,
        };
    }

    public static function labelFor(self|string|null $value): string
    {
        $status = self::fromValue($value);

        if ($status) {
            return $status->label();
        }

        $fallback = trim(str_replace('_', ' ', (string) $value));

        return $fallback !== '' ? ucfirst($fallback) : 'Status tidak dikenal';
    }

    public static function badgeClassesFor(self|string|null $value): string
    {
        return self::fromValue($value)?->badgeClasses() ?? 'bg-amber-100 text-amber-700';
    }
}
