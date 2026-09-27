<?php

namespace App\Enums;

enum PaymentStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Partial => 'Partial',
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Partial => 'sky',
            self::Paid => 'emerald',
            self::Refunded => 'rose',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Partial => 'adjustments-horizontal',
            self::Paid => 'check-circle',
            self::Refunded => 'arrow-uturn-left',
        };
    }
}
