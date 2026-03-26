<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Human-readable label for UI display. */
    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Returns true when the appointment can still be cancelled. */
    public function isCancellable(): bool
    {
        return match($this) {
            self::Pending, self::Confirmed => true,
            default                        => false,
        };
    }

    /** Returns true when the appointment is in a terminal state. */
    public function isTerminal(): bool
    {
        return match($this) {
            self::Completed, self::Cancelled => true,
            default                          => false,
        };
    }

    /** All values as a plain array — useful for validation rules. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
