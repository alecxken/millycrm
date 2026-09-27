<?php

namespace App\Enums;

enum BudgetBand: string
{
    use HasOptions;

    case Economy = 'economy';
    case Mid = 'mid';
    case Premium = 'premium';
    case Luxury = 'luxury';

    public function label(): string
    {
        return match ($this) {
            self::Economy => 'Economy',
            self::Mid => 'Mid-range',
            self::Premium => 'Premium',
            self::Luxury => 'Luxury',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Economy => 'slate',
            self::Mid => 'sky',
            self::Premium => 'violet',
            self::Luxury => 'amber',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Economy => 'banknotes',
            self::Mid => 'banknotes',
            self::Premium => 'banknotes',
            self::Luxury => 'banknotes',
        };
    }
}
