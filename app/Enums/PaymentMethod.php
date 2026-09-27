<?php

namespace App\Enums;

enum PaymentMethod: string
{
    use HasOptions;

    case Mpesa = 'mpesa';
    case Card = 'card';
    case Bank = 'bank';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Mpesa => 'M-Pesa',
            self::Card => 'Card',
            self::Bank => 'Bank transfer',
            self::Cash => 'Cash',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Mpesa => 'emerald',
            self::Card => 'sky',
            self::Bank => 'indigo',
            self::Cash => 'amber',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Mpesa => 'device-phone-mobile',
            self::Card => 'credit-card',
            self::Bank => 'building-library',
            self::Cash => 'banknotes',
        };
    }
}
