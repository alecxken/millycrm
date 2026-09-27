<?php

namespace App\Enums;

enum ReportFrequency: string
{
    use HasOptions;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Daily => 'sky',
            self::Weekly => 'teal',
            self::Monthly => 'violet',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Daily => 'sun',
            self::Weekly => 'calendar-days',
            self::Monthly => 'calendar',
        };
    }
}
