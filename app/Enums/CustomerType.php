<?php

namespace App\Enums;

enum CustomerType: string
{
    use HasOptions;

    case Individual = 'individual';
    case Corporate = 'corporate';
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Corporate => 'Corporate',
            self::Group => 'Group',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Individual => 'sky',
            self::Corporate => 'violet',
            self::Group => 'amber',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Individual => 'user',
            self::Corporate => 'building-office',
            self::Group => 'user-group',
        };
    }
}
