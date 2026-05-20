<?php

class Theme
{
    public const DEFAULT_PRIMARY = '#8b4513';
    public const DEFAULT_SECONDARY = '#f2b705';

    public static function current(): array
    {
        $primary = self::setting('theme_primary', self::DEFAULT_PRIMARY);
        $secondary = self::setting('theme_secondary', self::DEFAULT_SECONDARY);

        $primary = self::sanitizeHex($primary, self::DEFAULT_PRIMARY);
        $secondary = self::sanitizeHex($secondary, self::DEFAULT_SECONDARY);

        return [
            'primary' => $primary,
            'primary_dark' => self::darken($primary, 28),
            'primary_soft' => self::lighten($primary, 43),
            'primary_text' => self::bestTextColor($primary),
            'secondary' => $secondary,
            'secondary_dark' => self::darken($secondary, 25),
            'secondary_soft' => self::lighten($secondary, 40),
            'secondary_text' => self::bestTextColor($secondary),
            'focus_shadow' => self::rgba($primary, 0.18),
            'shadow_color' => self::rgba($primary, 0.12),
        ];
    }

    public static function save(string $primary, string $secondary): array
    {
        $primary = self::sanitizeHex($primary, '');
        $secondary = self::sanitizeHex($secondary, '');
        $errors = [];

        if ($primary === '') {
            $errors['primary'] = 'El color primario no es válido.';
        }

        if ($secondary === '') {
            $errors['secondary'] = 'El color secundario no es válido.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(<<<SQL
INSERT INTO app_settings (setting_key, setting_value)
VALUES (:setting_key, :setting_value)
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    updated_at = CURRENT_TIMESTAMP
SQL);

        $stmt->execute(['setting_key' => 'theme_primary', 'setting_value' => $primary]);
        $stmt->execute(['setting_key' => 'theme_secondary', 'setting_value' => $secondary]);

        return ['ok' => true, 'theme' => self::current()];
    }

    public static function cssVariables(): string
    {
        $theme = self::current();
        $lines = [];

        foreach ($theme as $key => $value) {
            $cssName = str_replace('_', '-', $key);
            $lines[] = "--{$cssName}: {$value};";
        }

        return ':root{' . implode('', $lines) . '}';
    }

    private static function setting(string $key, string $default): string
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);
        $value = $stmt->fetchColumn();
        return is_string($value) && $value !== '' ? $value : $default;
    }

    public static function sanitizeHex(string $color, string $fallback): string
    {
        $color = trim($color);

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtolower($color);
        }

        if (preg_match('/^[0-9a-fA-F]{6}$/', $color)) {
            return '#' . strtolower($color);
        }

        return $fallback;
    }

    public static function bestTextColor(string $hex): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $luminance = self::relativeLuminance($r, $g, $b);
        $contrastWhite = (1.05) / ($luminance + 0.05);
        $contrastBlack = ($luminance + 0.05) / 0.05;

        return $contrastWhite >= $contrastBlack ? '#ffffff' : '#1f2937';
    }

    public static function darken(string $hex, int $amount): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        return self::rgbToHex(
            max(0, $r - $amount),
            max(0, $g - $amount),
            max(0, $b - $amount)
        );
    }

    public static function lighten(string $hex, int $amount): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        return self::rgbToHex(
            min(255, $r + $amount),
            min(255, $g + $amount),
            min(255, $b + $amount)
        );
    }

    public static function rgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $alpha = max(0, min(1, $alpha));
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = self::sanitizeHex($hex, self::DEFAULT_PRIMARY);
        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }

    private static function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private static function relativeLuminance(int $r, int $g, int $b): float
    {
        $values = [$r / 255, $g / 255, $b / 255];
        foreach ($values as $index => $value) {
            $values[$index] = $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $values[0] + 0.7152 * $values[1] + 0.0722 * $values[2];
    }
}
