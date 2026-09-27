<?php

namespace App\Enums;

enum TicketStatus: string
{
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'sky',
            self::InProgress => 'amber',
            self::Resolved => 'emerald',
            self::Closed => 'slate',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Open => 'inbox',
            self::InProgress => 'arrow-path',
            self::Resolved => 'check-circle',
            self::Closed => 'lock-closed',
        };
    }
}
