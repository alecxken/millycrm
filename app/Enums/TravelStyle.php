<?php

namespace App\Enums;

enum TravelStyle: string
{
    use HasOptions;

    case Adventure = 'adventure';
    case Beach = 'beach';
    case Culture = 'culture';
    case Safari = 'safari';
    case Business = 'business';
    case Family = 'family';
    case Honeymoon = 'honeymoon';

    public function label(): string
    {
        return match ($this) {
            self::Adventure => 'Adventure',
            self::Beach => 'Beach',
            self::Culture => 'Culture',
            self::Safari => 'Safari',
            self::Business => 'Business',
            self::Family => 'Family',
            self::Honeymoon => 'Honeymoon',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Adventure => 'emerald',
            self::Beach => 'sky',
            self::Culture => 'violet',
            self::Safari => 'amber',
            self::Business => 'slate',
            self::Family => 'teal',
            self::Honeymoon => 'rose',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Adventure => 'fire',
            self::Beach => 'sun',
            self::Culture => 'building-library',
            self::Safari => 'camera',
            self::Business => 'briefcase',
            self::Family => 'home',
            self::Honeymoon => 'heart',
        };
    }
}
