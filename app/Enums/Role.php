<?php

namespace App\Enums;

enum Role: string
{
    use HasOptions;

    case Owner = 'owner';
    case Manager = 'manager';
    case Consultant = 'consultant';
    case Marketing = 'marketing';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Consultant => 'Consultant',
            self::Marketing => 'Marketing',
            self::Support => 'Support',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Owner => 'amber',
            self::Manager => 'violet',
            self::Consultant => 'teal',
            self::Marketing => 'rose',
            self::Support => 'sky',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Owner => 'key',
            self::Manager => 'briefcase',
            self::Consultant => 'user',
            self::Marketing => 'megaphone',
            self::Support => 'lifebuoy',
        };
    }
}
