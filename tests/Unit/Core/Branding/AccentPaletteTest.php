<?php

namespace Tests\Unit\Core\Branding;

use App\Core\Branding\AccentPalette;
use App\Core\Branding\InvalidAccentColor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccentPaletteTest extends TestCase
{
    public function test_default_accent_derives_the_documented_scale(): void
    {
        $palette = AccentPalette::fromHex('#b84f3f');

        $this->assertSame('#b84f3f', $palette->accent);
        $this->assertSame('#f9f1f0', $palette->subtle);
        $this->assertSame('#f4e5e2', $palette->muted);
        $this->assertSame('#dca79f', $palette->border);
        $this->assertSame('#9c4336', $palette->hover);
        $this->assertSame('#81372c', $palette->pressed);
        $this->assertSame('#ffffff', $palette->onAccent);
        $this->assertSame('#9c4336', $palette->text);
        $this->assertSame('#81372c', $palette->subtleText);
    }

    public function test_default_accent_contrast_matches_the_measured_ratios(): void
    {
        $this->assertSame(4.97, round(AccentPalette::contrastRatio('#ffffff', '#b84f3f'), 2));
        $this->assertSame(6.42, round(AccentPalette::contrastRatio('#9c4336', '#ffffff'), 2));
        $this->assertSame(7.5, round(AccentPalette::contrastRatio('#81372c', '#f9f1f0'), 2));
    }

    public function test_css_variables_expose_every_derived_shade(): void
    {
        $this->assertSame([
            '--brand-accent' => '#b84f3f',
            '--brand-accent-hover' => '#9c4336',
            '--brand-accent-pressed' => '#81372c',
            '--brand-accent-text' => '#9c4336',
            '--brand-on-accent' => '#ffffff',
            '--brand-accent-subtle' => '#f9f1f0',
            '--brand-accent-subtle-text' => '#81372c',
            '--brand-accent-muted' => '#f4e5e2',
            '--brand-accent-border' => '#dca79f',
        ], AccentPalette::fromHex('#b84f3f')->cssVariables());
    }

    public function test_dark_accent_keeps_white_labels_and_a_readable_text_shade(): void
    {
        $palette = AccentPalette::fromHex('#1f6f4a');

        $this->assertSame('#ffffff', $palette->onAccent);
        $this->assertSame('#1a5e3f', $palette->text);
        $this->assertGreaterThanOrEqual(4.5, AccentPalette::contrastRatio($palette->text, '#ffffff'));
    }

    /** @return array<string, array{string}> */
    public static function unreadableAccents(): array
    {
        return [
            'old coral fails 3:1 on white' => ['#ea7c69'],
            'yellow is invisible on white' => ['#ffe066'],
            'orange cannot hold a 4.5:1 label' => ['#c2581c'],
            'white' => ['#ffffff'],
        ];
    }

    #[DataProvider('unreadableAccents')]
    public function test_accents_that_cannot_stay_readable_on_white_are_rejected(string $hex): void
    {
        $this->expectException(InvalidAccentColor::class);

        AccentPalette::fromHex($hex);
    }

    /** @return array<string, array{string}> */
    public static function malformedAccents(): array
    {
        return [
            'missing hash' => ['b84f3f'],
            'uppercase' => ['#B84F3F'],
            'short form' => ['#b43'],
            'css injection' => ['#b84f3f;}body{'],
        ];
    }

    #[DataProvider('malformedAccents')]
    public function test_malformed_accents_are_rejected(string $hex): void
    {
        $this->expectException(InvalidAccentColor::class);

        AccentPalette::fromHex($hex);
    }
}
