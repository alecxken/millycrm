<?php

namespace App\Enums;

/**
 * Shared helpers for backed enums used as status and type fields.
 * Every status is rendered as colour + label + icon (never colour alone).
 */
trait HasOptions
{
    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
