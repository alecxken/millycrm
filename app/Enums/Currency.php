<?php

namespace App\Enums;

enum Currency: string
{
    use HasOptions;

    case KES = 'KES';
    case USD = 'USD';
    case AUD = 'AUD';

    public function label(): string
    {
        return match ($this) {
            self::KES => 'Kenya Shilling',
            self::USD => 'US Dollar',
            self::AUD => 'Australian Dollar',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::KES => 'teal',
            self::USD => 'emerald',
            self::AUD => 'sky',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::KES => 'banknotes',
            self::USD => 'currency-dollar',
            self::AUD => 'currency-dollar',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::KES => 'KES',
            self::USD => 'US$',
            self::AUD => 'A$',
        };
    }

    /** Indicative rate to KES for reporting roll-ups. */
    public function toKes(): float
    {
        return match ($this) {
            self::KES => 1.0,
            self::USD => 129.0,
            self::AUD => 85.0,
        };
    }
}
