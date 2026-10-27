<?php

namespace App\Enums;

enum DecisionStatus: string
{
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REVISION_REQUIRED = 'revision_required';
    case RETURNED_TO_ADMIN = 'returned_to_admin';

    public function label(): string
    {
        return match ($this) {
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::REVISION_REQUIRED => 'Revisi',
            self::RETURNED_TO_ADMIN => 'Dikembalikan ke Admin',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }

    /**
     * Pilihan untuk alur baru. returned_to_admin hanya dipertahankan untuk
     * membaca data lama dan tidak boleh dipilih lagi.
     *
     * @return array<int, self>
     */
    public static function formOptions(): array
    {
        return [self::APPROVED, self::REJECTED, self::REVISION_REQUIRED];
    }

    /**
     * @return array<int, string>
     */
    public static function formValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::formOptions());
    }

    public static function labelFor(self|string|null $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        return match ($value) {
            'approved' => self::APPROVED->label(),
            'rejected' => self::REJECTED->label(),
            'revision_required', 'needs_revision', 'revision_requested' => self::REVISION_REQUIRED->label(),
            'returned_to_admin' => self::RETURNED_TO_ADMIN->label(),
            default => $value ? ucfirst(str_replace('_', ' ', $value)) : 'Tidak ada keputusan',
        };
    }
}
