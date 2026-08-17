<?php

declare(strict_types=1);

use Refilament\Refilament\Support\Colors\Color;
use Refilament\Refilament\Support\Colors\ColorManager;

it('exposes the full named palette set', function (): void {
    $colors = Color::all();

    expect($colors)->toHaveKey('red')
        ->and($colors)->toHaveKey('slate')
        ->and($colors['red'])->toHaveCount(11)
        ->and($colors['blue'][500])->toStartWith('oklch(');
});

it('converts between hex, rgb and oklch', function (): void {
    expect(Color::convertToOklch('#ffffff'))->toStartWith('oklch(')
        ->and(Color::convertToRgb('#ff0000'))->toBe('rgb(255, 0, 0)')
        ->and(Color::convertToHex('rgb(255, 0, 0)'))->toBe('#ff0000');
});

it('calculates WCAG contrast ratios', function (): void {
    $ratio = Color::calculateContrastRatio('#ffffff', '#000000');

    expect($ratio)->toBeGreaterThan(15.0)
        ->and(Color::isTextContrastRatioAccessible('#ffffff', '#000000'))->toBeTrue()
        ->and(Color::isNonTextContrastRatioAccessible('#ffffff', '#000000'))->toBeTrue();
});

it('flags light colors', function (): void {
    expect(Color::isLight('#ffffff'))->toBeTrue()
        ->and(Color::isLight('#000000'))->toBeFalse();
});

it('generates a palette from a single color', function (): void {
    $palette = Color::generatePalette('#e11d48');

    expect($palette)->toHaveCount(11)
        ->and($palette[500])->toStartWith('oklch(');
});

it('finds a shade with sufficient contrast', function (): void {
    $palette = Color::Slate;

    $shade = Color::findShade($palette, '#ffffff');

    expect($shade)->toBeInt();
});

it('registers named colors with defaults', function (): void {
    $manager = new ColorManager;

    $colors = $manager->getColors();

    expect($colors)->toHaveKey('primary')
        ->and($colors['danger'])->toHaveCount(11)
        ->and($colors['danger'][500])->toStartWith('oklch(');
});

it('registers a custom named color from a hex string', function (): void {
    $manager = new ColorManager;

    $manager->register(['brand' => '#e11d48']);

    expect($manager->getColor('brand'))->toHaveCount(11)
        ->and($manager->getColor('brand')[500])->toStartWith('oklch(');
});

it('registers a custom named color from a closure', function (): void {
    $manager = new ColorManager;

    $manager->register(fn (): array => ['custom' => Color::Green]);

    expect($manager->getColor('custom'))->toHaveCount(11);
});

it('registers a literal palette without re-generating it', function (): void {
    $manager = new ColorManager;

    $manager->register(['custom' => [500 => 'oklch(0.6 0.2 20)']]);

    expect($manager->getColor('custom'))->toBe([500 => 'oklch(0.6 0.2 20)']);
});

it('tracks shade overrides', function (): void {
    $manager = new ColorManager;

    $manager->overrideShades('red', [600, 700]);
    $manager->addShades('red', [650]);
    $manager->removeShades('red', [950]);

    expect($manager->getOverridingShades('red'))->toBe([600, 700])
        ->and($manager->getAddedShades('red'))->toBe([650])
        ->and($manager->getRemovedShades('red'))->toBe([950])
        ->and($manager->getOverridingShades('missing'))->toBeNull();
});

it('returns null for an unknown color', function (): void {
    $manager = new ColorManager;

    expect($manager->getColor('does-not-exist'))->toBeNull();
});
