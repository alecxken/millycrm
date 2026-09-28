<?php

namespace App\Support;

/** Brand-consistent colours for Chart.js datasets (validated for AA on light & dark). */
final class ChartPalette
{
    /* Theme tokens: resolved to real colours in the browser (resources/js/app.js), so charts follow Settings → Appearance. */
    public const TEAL = 'var(--color-brand-700)';

    public const SAND = 'var(--accent)';

    public const SERIES = ['var(--color-brand-700)', 'var(--accent)', '#0EA5E9', '#8B5CF6', '#E11D48', '#10B981', '#64748B', 'var(--color-brand-400)'];

    public static function series(int $count): array
    {
        return array_map(fn ($i) => self::SERIES[$i % count(self::SERIES)], range(0, max($count - 1, 0)));
    }

    public static function bar(array $data, string $label, string|array|null $color = null, bool $horizontal = false, bool $currency = false): array
    {
        return [
            'type' => 'bar',
            'data' => [
                'labels' => array_keys($data),
                'datasets' => [[
                    'label' => $label,
                    'data' => array_values($data),
                    'backgroundColor' => $color ?? self::TEAL,
                    'borderRadius' => 6,
                    'maxBarThickness' => 36,
                ]],
            ],
            'options' => array_filter([
                'indexAxis' => $horizontal ? 'y' : 'x',
                'plugins' => ['legend' => ['display' => false]],
                'currency' => $currency ?: null,
            ]),
        ];
    }

    public static function doughnut(array $data, ?array $colors = null): array
    {
        return [
            'type' => 'doughnut',
            'data' => [
                'labels' => array_keys($data),
                'datasets' => [[
                    'data' => array_values($data),
                    'backgroundColor' => $colors ?? self::series(count($data)),
                    'borderWidth' => 0,
                ]],
            ],
            'options' => ['cutout' => '65%', 'plugins' => ['legend' => ['position' => 'bottom']]],
        ];
    }

    public static function line(array $data, string $label, bool $currency = true): array
    {
        return [
            'type' => 'line',
            'data' => [
                'labels' => array_keys($data),
                'datasets' => [[
                    'label' => $label,
                    'data' => array_values($data),
                    'borderColor' => self::TEAL,
                    'backgroundColor' => 'var(--color-brand-100)',
                    'fill' => true,
                    'tension' => 0.3,
                    'cubicInterpolationMode' => 'monotone',
                    'pointRadius' => 3,
                    'pointBackgroundColor' => self::SAND,
                ]],
            ],
            'options' => array_filter(['plugins' => ['legend' => ['display' => false]], 'currency' => $currency ?: null]),
        ];
    }
}
