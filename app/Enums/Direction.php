<?php

namespace App\Enums;

enum Direction: string
{
    use HasOptions;

    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::Inbound => 'Inbound',
            self::Outbound => 'Outbound',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Inbound => 'sky',
            self::Outbound => 'teal',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Inbound => 'arrow-down-left',
            self::Outbound => 'arrow-up-right',
        };
    }
}
