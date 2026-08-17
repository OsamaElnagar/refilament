<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\Textarea;

it('serializes a plain textarea without rows', function () {
    expect(Textarea::make('excerpt')->toArray())->toBe([
        'type' => 'textarea',
        'name' => 'excerpt',
        'label' => 'Excerpt',
    ]);
});

it('serializes rows only when set', function () {
    $node = Textarea::make('excerpt')->rows(6)->toArray();

    expect($node['rows'])->toBe(6);
});

it('clamps rows to a minimum of one', function () {
    expect(Textarea::make('excerpt')->rows(0)->toArray()['rows'])->toBe(1);
    expect(Textarea::make('excerpt')->rows(-3)->toArray()['rows'])->toBe(1);
});

it('inherits the shared field config', function () {
    $node = Textarea::make('bio')
        ->label('Biography')
        ->helperText('Tell us about yourself')
        ->placeholder('A few sentences…')
        ->maxLength(500)
        ->rules(['string', 'max:500'])
        ->required()
        ->columnSpan(2)
        ->toArray();

    expect($node['label'])->toBe('Biography');
    expect($node['helperText'])->toBe('Tell us about yourself');
    expect($node['maxLength'])->toBe(500);
    expect($node['validation'])->toBe(['required', 'string', 'max:500']);
    expect($node['columnSpan'])->toBe(2);
});
