<?php

namespace App\Enums;

enum QuoteItemType: string
{
    use HasOptions;

    case Flight = 'flight';
    case Hotel = 'hotel';
    case Tour = 'tour';
    case Transfer = 'transfer';
    case Insurance = 'insurance';
    case Visa = 'visa';

    public function label(): string
    {
        return match ($this) {
            self::Flight => 'Flight',
            self::Hotel => 'Hotel',
            self::Tour => 'Tour / safari',
            self::Transfer => 'Transfer',
            self::Insurance => 'Insurance',
            self::Visa => 'Visa',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Flight => 'sky',
            self::Hotel => 'violet',
            self::Tour => 'amber',
            self::Transfer => 'slate',
            self::Insurance => 'emerald',
            self::Visa => 'indigo',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Flight => 'paper-airplane',
            self::Hotel => 'building-office-2',
            self::Tour => 'map',
            self::Transfer => 'truck',
            self::Insurance => 'shield-check',
            self::Visa => 'identification',
        };
    }
}
