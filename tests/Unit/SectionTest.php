<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\Section;
use Refilament\Refilament\Schemas\Components\TextInput;

it('serializes a section with heading, description and children', function () {
    $node = Section::make()
        ->heading('Location')
        ->description('Pick your country')
        ->schema([TextInput::make('city')])
        ->toArray();

    expect($node)->toBe([
        'type' => 'section',
        'heading' => 'Location',
        'description' => 'Pick your country',
        'schema' => [
            [
                'type' => 'text_input',
                'name' => 'city',
                'label' => 'City',
            ],
        ],
    ]);
});

it('omits heading and description when not set', function () {
    $node = Section::make()->schema([TextInput::make('city')])->toArray();

    expect($node)->not->toHaveKey('heading');
    expect($node)->not->toHaveKey('description');
    expect($node)->toHaveKey('schema');
});

it('always serializes children even when empty', function () {
    $node = Section::make()->toArray();

    expect($node['schema'])->toBe([]);
});
