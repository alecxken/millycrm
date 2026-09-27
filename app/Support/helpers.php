<?php

use App\Enums\Currency;
use Carbon\CarbonInterface;

if (! function_exists('money')) {
    /**
     * Locale-aware currency formatting. KES by default: "KES 125,000".
     */
    function money(float|int|string|null $amount, Currency|string|null $currency = null, bool $compact = false): string
    {
        $currency = $currency instanceof Currency ? $currency : Currency::tryFrom((string) $currency) ?? Currency::KES;
        $amount = (float) $amount;

        if ($compact && abs($amount) >= 1000) {
            $units = ['', 'K', 'M', 'B'];
            $power = min((int) floor(log10(abs($amount)) / 3), 3);
            $value = $amount / (1000 ** $power);

            return $currency->symbol().' '.rtrim(rtrim(number_format($value, $value < 10 ? 1 : 0), '0'), '.').$units[$power];
        }

        return $currency->symbol().' '.number_format($amount, $currency === Currency::KES ? 0 : 2);
    }
}

if (! function_exists('fdate')) {
    /** "26 Sep 2026" (optionally with time). */
    function fdate(?CarbonInterface $date, bool $withTime = false): string
    {
        if ($date === null) {
            return '—';
        }

        return $date->format($withTime ? 'j M Y, H:i' : 'j M Y');
    }
}
