<?php

namespace App\Enums;

enum SlotStatus: string
{
    case Available = 'available';
    case Booked    = 'booked';
    case Blocked   = 'blocked';

    /** Human-readable label for UI display. */
    public function label(): string
    {
        return match($this) {
            self::Available => 'Available',
            self::Booked    => 'Booked',
            self::Blocked   => 'Blocked',
        };
    }

    /** Returns true only when a patient can book this slot. */
    public function isBookable(): bool
    {
        return $this === self::Available;
    }

    /** All values as a plain array — useful for validation rules. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
