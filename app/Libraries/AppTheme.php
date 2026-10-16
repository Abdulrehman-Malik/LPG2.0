<?php

namespace App\Libraries;

/**
 * Turns a branch's appearance settings (shop_settings: theme_mode, font_family, primary_color,
 * accent_color, pos_font_size_px) into the CSS custom properties used by public/assets/css/app.css.
 * Foreground colours are derived so text stays readable whatever colours a branch picks.
 */
final class AppTheme
{
    public const DEFAULT_PRIMARY = '#1b2a3a';
    public const DEFAULT_ACCENT = '#ff7a1a';

    /** Selectable font styles: key stored in shop_settings.font_family => [label, CSS stack]. */
    public const FONTS = [
        'system'    => ['Inter (default)', '"Inter Variable",Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif'],
        'device'    => ['Device system font', 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif'],
        'arial'     => ['Arial', 'Arial,Helvetica,sans-serif'],
        'verdana'   => ['Verdana', 'Verdana,Geneva,sans-serif'],
        'tahoma'    => ['Tahoma', 'Tahoma,Geneva,sans-serif'],
        'trebuchet' => ['Trebuchet MS', '"Trebuchet MS",sans-serif'],
        'georgia'   => ['Georgia (serif)', 'Georgia,serif'],
        'times'     => ['Times New Roman (serif)', '"Times New Roman",Times,serif'],
    ];

    /** @return array<string,string> key => label */
    public static function fontOptions(): array
    {
        return array_map(static fn (array $font): string => $font[0], self::FONTS);
    }

    public static function fontStack(?string $key): string
    {
        return (self::FONTS[$key ?? 'system'] ?? self::FONTS['system'])[1];
    }

    public static function themeMode(array $settings): string
    {
        return ($settings['theme_mode'] ?? 'light') === 'dark' ? 'dark' : 'light';
    }

    /** CSS declarations for :root, built only from validated values. */
    public static function cssVariables(array $settings): string
    {
        $primary = self::hex($settings['primary_color'] ?? null, self::DEFAULT_PRIMARY);
        $accent = self::hex($settings['accent_color'] ?? null, self::DEFAULT_ACCENT);
        $fontSize = (float) ($settings['pos_font_size_px'] ?? 14);
        $fontSize = $fontSize >= 10 && $fontSize <= 24 ? $fontSize : 14;
        $sidebarFg = self::readableOn($primary);
        $accentStrong = self::shadeForContrast($accent, '#ffffff', 4.5);

        $vars = [
            '--lpg-font' => self::fontStack($settings['font_family'] ?? 'system'),
            '--lpg-font-size' => rtrim(rtrim(number_format($fontSize, 1, '.', ''), '0'), '.') . 'px',
            '--lpg-brand' => $primary,
            '--lpg-sidebar-fg' => $sidebarFg,
            '--lpg-sidebar-muted' => $sidebarFg === '#ffffff' ? 'rgba(255,255,255,.68)' : 'rgba(15,23,42,.62)',
            '--lpg-sidebar-hover' => $sidebarFg === '#ffffff' ? 'rgba(255,255,255,.08)' : 'rgba(15,23,42,.06)',
            '--lpg-accent' => $accent,
            '--lpg-accent-rgb' => implode(',', self::rgb($accent)),
            '--lpg-accent-contrast' => self::readableOn($accent),
            '--lpg-accent-strong' => $accentStrong,
            '--lpg-accent-strong-rgb' => implode(',', self::rgb($accentStrong)),
        ];
        $css = '';
        foreach ($vars as $name => $value) {
            $css .= $name . ':' . $value . ';';
        }
        return $css;
    }

    public static function hex(?string $value, string $default): string
    {
        $value = trim((string) $value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
    }

    /** White or near-black, whichever reads better on the given background. */
    public static function readableOn(string $hex): string
    {
        return self::contrast($hex, '#ffffff') >= self::contrast($hex, '#0f172a') ? '#ffffff' : '#0f172a';
    }

    /** Darkens $hex until it reaches $ratio contrast against $background (for links/text on white). */
    public static function shadeForContrast(string $hex, string $background, float $ratio): string
    {
        [$r, $g, $b] = self::rgb($hex);
        for ($i = 0; $i < 20 && self::contrast(sprintf('#%02x%02x%02x', $r, $g, $b), $background) < $ratio; $i++) {
            $r = (int) round($r * 0.9);
            $g = (int) round($g * 0.9);
            $b = (int) round($b * 0.9);
        }
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** WCAG contrast ratio between two colours. */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** @return array{int,int,int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(static function (int $c): float {
            $c /= 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
