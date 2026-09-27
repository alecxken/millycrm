<?php

namespace App\Models\Concerns;

/**
 * Generates short, human-friendly references (e.g. BK-10234) that staff
 * can read over the phone and search for in the command palette.
 */
trait HasReference
{
    public static function bootHasReference(): void
    {
        static::creating(function ($model) {
            if (empty($model->reference)) {
                $next = (int) static::withoutGlobalScopes()->max('id') + 10001;
                $model->reference = static::REFERENCE_PREFIX.'-'.$next;
            }
        });
    }
}
