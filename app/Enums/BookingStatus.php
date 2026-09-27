<?php

namespace App\Enums;

enum BookingStatus: string
{
    use HasOptions;

    case Confirmed = 'confirmed';
    case Travelling = 'travelling';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Travelling => 'Travelling',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'teal',
            self::Travelling => 'sky',
            self::Completed => 'emerald',
            self::Cancelled => 'rose',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Confirmed => 'check-badge',
            self::Travelling => 'paper-airplane',
            self::Completed => 'flag',
            self::Cancelled => 'x-circle',
        };
    }
}
