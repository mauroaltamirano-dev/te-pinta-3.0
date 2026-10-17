<?php

namespace App\Core\Branding;

final readonly class AccentPalette
{
    public const DEFAULT = '#b84f3f';

    private function __construct(
        public string $accent,
        public string $hover,
        public string $pressed,
        public string $text,
        public string $onAccent,
        public string $subtle,
        public string $subtleText,
        public string $muted,
        public string $border,
    ) {}

    public static function fromHex(string $hex): self
    {
        if (preg_match('/\A#[0-9a-f]{6}\z/', $hex) !== 1) {
            throw new InvalidAccentColor('Ingresá un color hexadecimal de seis dígitos, por ejemplo #b84f3f.');
        }

        $contrast = self::contrastRatio($hex, '#ffffff');

        if ($contrast < 3) {
            throw new InvalidAccentColor('El color de acento no se distingue lo suficiente sobre fondo blanco (mínimo 3:1). Elegí un tono más oscuro.');
        }

        if ($contrast < 4.5) {
            throw new InvalidAccentColor('El texto sobre el color de acento no alcanza contraste 4.5:1. Elegí un tono más oscuro.');
        }

        $hover = self::mix($hex, '#000000', 0.85);
        $pressed = self::mix($hex, '#000000', 0.70);

        return new self(
            $hex, $hover, $pressed, $hover, '#ffffff',
            self::mix($hex, '#ffffff', 0.08), $pressed,
            self::mix($hex, '#ffffff', 0.15),
            self::mix($hex, '#ffffff', 0.50),
        );
    }

    /** @return array<string, string> */
    public function cssVariables(): array
    {
        return [
            '--brand-accent' => $this->accent,
            '--brand-accent-hover' => $this->hover,
            '--brand-accent-pressed' => $this->pressed,
            '--brand-accent-text' => $this->text,
            '--brand-on-accent' => $this->onAccent,
            '--brand-accent-subtle' => $this->subtle,
            '--brand-accent-subtle-text' => $this->subtleText,
            '--brand-accent-muted' => $this->muted,
            '--brand-accent-border' => $this->border,
        ];
    }

    public static function contrastRatio(string $first, string $second): float
    {
        $a = self::luminance($first);
        $b = self::luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::channels($hex));

        return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
    }

    /** @return array{int, int, int} */
    private static function channels(string $hex): array
    {
        return [intval(substr($hex, 1, 2), 16), intval(substr($hex, 3, 2), 16), intval(substr($hex, 5, 2), 16)];
    }

    private static function mix(string $hex, string $base, float $weight): string
    {
        $color = self::channels($hex);
        $background = self::channels($base);
        $channels = array_map(static fn (int $i): int => (int) round(
            $color[$i] * $weight + $background[$i] * (1 - $weight),
        ), [0, 1, 2]);

        return sprintf('#%02x%02x%02x', ...$channels);
    }
}
