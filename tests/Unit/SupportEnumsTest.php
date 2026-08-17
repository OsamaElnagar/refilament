<?php

declare(strict_types=1);

use Refilament\Refilament\Support\Enums\Alignment;
use Refilament\Refilament\Support\Enums\FontFamily;
use Refilament\Refilament\Support\Enums\FontWeight;
use Refilament\Refilament\Support\Enums\IconPosition;
use Refilament\Refilament\Support\Enums\Platform;
use Refilament\Refilament\Support\Enums\Size;
use Refilament\Refilament\Support\Enums\TextSize;
use Refilament\Refilament\Support\Enums\VerticalAlignment;
use Refilament\Refilament\Support\Enums\Width;

it('is a string-backed enum', function (string $enum, string $value): void {
    expect($enum::tryFrom($value))->not->toBeNull();
})->with([
    'Alignment' => [Alignment::class, 'start'],
    'IconPosition' => [IconPosition::class, 'before'],
    'Size' => [Size::class, 'md'],
    'TextSize' => [TextSize::class, 'lg'],
    'FontFamily' => [FontFamily::class, 'mono'],
    'FontWeight' => [FontWeight::class, 'bold'],
    'VerticalAlignment' => [VerticalAlignment::class, 'center'],
    'Width' => [Width::class, 'screen-lg'],
]);

it('exposes the expected canonical values', function (): void {
    expect(Alignment::Start->value)->toBe('start')
        ->and(Alignment::Justify->value)->toBe('justify')
        ->and(IconPosition::After->value)->toBe('after')
        ->and(Size::ExtraLarge->value)->toBe('xl')
        ->and(FontWeight::Black->value)->toBe('black')
        ->and(Width::Full->value)->toBe('full')
        ->and(Width::Screen->value)->toBe('screen');
});

it('detects the platform from a user agent', function (): void {
    expect(Platform::detect('Mozilla/5.0 (Windows NT 10.0; Win64; x64)'))->toBe(Platform::Windows)
        ->and(Platform::detect('Mozilla/5.0 (Macintosh; Intel Mac OS X)'))->toBe(Platform::Mac)
        ->and(Platform::detect('Mozilla/5.0 (X11; Linux x86_64)'))->toBe(Platform::Linux)
        ->and(Platform::detect('Some unknown agent'))->toBe(Platform::Other);
});
