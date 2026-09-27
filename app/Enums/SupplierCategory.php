<?php

namespace App\Enums;

enum SupplierCategory: string
{
    use HasOptions;

    case Airline = 'airline';
    case Hotel = 'hotel';
    case TourOperator = 'tour_operator';
    case Insurer = 'insurer';
    case Transfer = 'transfer';
    case VisaAgent = 'visa_agent';

    public function label(): string
    {
        return match ($this) {
            self::Airline => 'Airline',
            self::Hotel => 'Hotel',
            self::TourOperator => 'Tour operator',
            self::Insurer => 'Insurer',
            self::Transfer => 'Transfer',
            self::VisaAgent => 'Visa agent',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Airline => 'sky',
            self::Hotel => 'violet',
            self::TourOperator => 'amber',
            self::Insurer => 'emerald',
            self::Transfer => 'slate',
            self::VisaAgent => 'indigo',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Airline => 'paper-airplane',
            self::Hotel => 'building-office-2',
            self::TourOperator => 'map',
            self::Insurer => 'shield-check',
            self::Transfer => 'truck',
            self::VisaAgent => 'identification',
        };
    }
}
