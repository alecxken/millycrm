<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Agency-wide appearance. Everything visual reads from a handful of CSS
 * variables (--brand, --accent, --font-sans, --radius-scale), so the owner can
 * re-brand the whole app from Settings → Appearance without a rebuild.
 * Shades (brand-50 … brand-950) are derived in CSS with color-mix().
 */
class ThemeService
{
    public const FONTS = [
        'quicksand' => ['label' => 'Quicksand', 'stack' => "'Quicksand', ui-sans-serif, system-ui, sans-serif", 'note' => 'Round and friendly (default)'],
        'nunito' => ['label' => 'Nunito', 'stack' => "'Nunito', ui-sans-serif, system-ui, sans-serif", 'note' => 'Soft, very readable'],
        'jakarta' => ['label' => 'Plus Jakarta Sans', 'stack' => "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif", 'note' => 'Modern and crisp'],
        'inter' => ['label' => 'Inter', 'stack' => "'Inter', ui-sans-serif, system-ui, sans-serif", 'note' => 'Neutral, dense UI'],
        'system' => ['label' => 'System font', 'stack' => "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif", 'note' => 'Whatever the device uses'],
    ];

    public const CORNERS = [
        'soft' => ['label' => 'Soft', 'scale' => 1.0, 'note' => 'Large, friendly corners'],
        'balanced' => ['label' => 'Balanced', 'scale' => 0.7, 'note' => 'A little tighter'],
        'crisp' => ['label' => 'Crisp', 'scale' => 0.4, 'note' => 'Nearly square'],
    ];

    public const PRESETS = [
        'wanderlink' => ['label' => 'WanderLink', 'brand' => '#0F766E', 'accent' => '#F59E0B', 'note' => 'Savannah teal and sand'],
        'ocean' => ['label' => 'Ocean', 'brand' => '#0082BB', 'accent' => '#BED600', 'note' => 'Deep blue with a lime spark'],
        'sunset' => ['label' => 'Sunset', 'brand' => '#C2410C', 'accent' => '#FBBF24', 'note' => 'Warm terracotta'],
        'jacaranda' => ['label' => 'Jacaranda', 'brand' => '#6D28D9', 'accent' => '#F472B6', 'note' => 'Nairobi in October'],
        'forest' => ['label' => 'Forest', 'brand' => '#3F6212', 'accent' => '#EAB308', 'note' => 'Aberdare greens'],
        'midnight' => ['label' => 'Midnight', 'brand' => '#1E3A8A', 'accent' => '#22D3EE', 'note' => 'Calm, corporate'],
    ];

    public const DEFAULTS = [
        'preset' => 'wanderlink',
        'brand' => '#0F766E',
        'accent' => '#F59E0B',
        'font' => 'quicksand',
        'corners' => 'soft',
        'wash' => true,
    ];

    public function current(): array
    {
        return array_merge(self::DEFAULTS, (array) Setting::get('theme', []));
    }

    public function save(array $theme): array
    {
        $theme = array_merge(self::DEFAULTS, array_intersect_key($theme, self::DEFAULTS));
        Setting::put('theme', $theme);

        return $theme;
    }

    public function reset(): array
    {
        return $this->save(self::DEFAULTS);
    }

    /** CSS custom properties for the <head>. Values are validated before saving. */
    public function cssVariables(?array $theme = null): string
    {
        $t = $theme ?? $this->current();
        $font = self::FONTS[$t['font']] ?? self::FONTS['quicksand'];
        $corners = self::CORNERS[$t['corners']] ?? self::CORNERS['soft'];

        $brand = $this->hex($t['brand'], self::DEFAULTS['brand']);
        $accent = $this->hex($t['accent'], self::DEFAULTS['accent']);

        return sprintf(
            ':root{--brand:%s;--brand-ink:%s;--accent:%s;--accent-ink:%s;--app-font:%s;--radius-scale:%s;--wash-opacity:%s}',
            $brand, self::readableOn($brand), $accent, self::readableOn($accent),
            $font['stack'], $corners['scale'], $t['wash'] ? '1' : '0',
        );
    }

    /** Text colour (#fff or near-black) that stays readable on the given background. */
    public static function readableOn(string $hex): string
    {
        if (! preg_match('/^#?[0-9a-fA-F]{6}$/', $hex)) {
            return '#FFFFFF'; // half-typed or invalid colour: assume a dark background
        }

        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
        $lin = fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $l = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return $l > 0.45 ? '#1F2937' : '#FFFFFF';
    }

    private function hex(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : $fallback;
    }
}
