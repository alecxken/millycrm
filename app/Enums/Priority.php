<?php

namespace App\Enums;

enum Priority: string
{
    use HasOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'slate',
            self::Normal => 'sky',
            self::High => 'amber',
            self::Urgent => 'rose',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Low => 'chevron-down',
            self::Normal => 'minus',
            self::High => 'chevron-up',
            self::Urgent => 'exclamation-triangle',
        };
    }

    public function slaHours(): int
    {
        return match ($this) {
            self::Urgent => 4,
            self::High => 24,
            self::Normal => 48,
            self::Low => 72,
        };
    }
}
