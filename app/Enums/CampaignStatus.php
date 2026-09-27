<?php

namespace App\Enums;

enum CampaignStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Sent => 'Sent',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Scheduled => 'amber',
            self::Sent => 'emerald',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'pencil-square',
            self::Scheduled => 'calendar',
            self::Sent => 'paper-airplane',
        };
    }
}
