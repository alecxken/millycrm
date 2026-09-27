<?php

namespace App\Enums;

enum LifecycleStage: string
{
    use HasOptions;

    case Lead = 'lead';
    case Prospect = 'prospect';
    case Customer = 'customer';
    case Repeat = 'repeat';
    case Vip = 'vip';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Prospect => 'Prospect',
            self::Customer => 'Customer',
            self::Repeat => 'Repeat',
            self::Vip => 'VIP',
            self::Inactive => 'Inactive',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Lead => 'slate',
            self::Prospect => 'sky',
            self::Customer => 'teal',
            self::Repeat => 'emerald',
            self::Vip => 'amber',
            self::Inactive => 'rose',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Lead => 'sparkles',
            self::Prospect => 'magnifying-glass',
            self::Customer => 'check-badge',
            self::Repeat => 'arrow-path',
            self::Vip => 'star',
            self::Inactive => 'moon',
        };
    }

    /** Ordering used so automatic promotion never demotes a customer. */
    public function rank(): int
    {
        return match ($this) {
            self::Inactive => 0,
            self::Lead => 1,
            self::Prospect => 2,
            self::Customer => 3,
            self::Repeat => 4,
            self::Vip => 5,
        };
    }
}
