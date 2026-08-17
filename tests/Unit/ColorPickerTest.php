<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\ColorPicker;

it('returns the color_picker type', function () {
    expect(ColorPicker::make('accent')->getType())->toBe('color_picker');
});

it('defaults to hex format', function () {
    $node = ColorPicker::make('accent')->toArray();

    expect($node['format'])->toBe('hex');
});

it('serializes the chosen format', function (string $format) {
    $node = ColorPicker::make('accent')->format($format)->toArray();

    expect($node['format'])->toBe($format);
})->with(['hex', 'hsl', 'rgb', 'rgba']);

it('supports the convenience format methods', function () {
    expect(ColorPicker::make('a')->hex()->getFormat())->toBe('hex');
    expect(ColorPicker::make('a')->hsl()->getFormat())->toBe('hsl');
    expect(ColorPicker::make('a')->rgb()->getFormat())->toBe('rgb');
    expect(ColorPicker::make('a')->rgba()->getFormat())->toBe('rgba');
});
