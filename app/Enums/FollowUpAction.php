<?php

namespace App\Enums;

enum FollowUpAction: string
{
    case NONE = 'none';
    case NEW_RFC = 'new_rfc';
    case RESCHEDULE = 'reschedule';
    case ESCALATION = 'escalation';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Tidak ada tindak lanjut',
            self::NEW_RFC => 'Ajukan RFC baru',
            self::RESCHEDULE => 'Jadwalkan ulang',
            self::ESCALATION => 'Eskalasi atau investigasi',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $action) => $action->value, self::cases());
    }

    public static function labelFor(self|string|null $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        return match ($value) {
            self::NONE->value => self::NONE->label(),
            self::NEW_RFC->value => self::NEW_RFC->label(),
            self::RESCHEDULE->value => self::RESCHEDULE->label(),
            self::ESCALATION->value => self::ESCALATION->label(),
            default => 'Tindak lanjut tidak dikenal',
        };
    }
}
