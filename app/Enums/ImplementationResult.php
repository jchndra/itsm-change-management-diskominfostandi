<?php

namespace App\Enums;

enum ImplementationResult: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case ROLLED_BACK = 'rolled_back';

    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => 'Berhasil',
            self::FAILED => 'Reject',
            self::ROLLED_BACK => 'Rollback dilakukan',
        };
    }

    /**
     * Nilai hasil yang boleh dipilih untuk laporan implementasi baru.
     *
     * ROLLED_BACK hanya dipertahankan untuk membaca data lama dan tidak lagi
     * ditawarkan sebagai opsi laporan.
     *
     * @return array<int, string>
     */
    public static function reportableValues(): array
    {
        return [self::SUCCESS->value, self::FAILED->value];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $result) => $result->value, self::cases());
    }

    public static function labelFor(self|string|null $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        return match ($value) {
            self::SUCCESS->value => self::SUCCESS->label(),
            self::FAILED->value => self::FAILED->label(),
            self::ROLLED_BACK->value => self::ROLLED_BACK->label(),
            default => 'Hasil implementasi tidak dikenal',
        };
    }
}
