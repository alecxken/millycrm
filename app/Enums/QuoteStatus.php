<?php

namespace App\Enums;

enum QuoteStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Sent => 'sky',
            self::Accepted => 'emerald',
            self::Rejected => 'rose',
            self::Expired => 'amber',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'pencil-square',
            self::Sent => 'paper-airplane',
            self::Accepted => 'check-circle',
            self::Rejected => 'x-circle',
            self::Expired => 'clock',
        };
    }
}
