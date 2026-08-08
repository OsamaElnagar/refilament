<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\Grid;
use Refilament\Refilament\Schemas\Components\Select;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;

it('serializes a grid with children', function () {
    $node = Grid::make()->columns(2)->schema([
        TextInput::make('title'),
        Select::make('status'),
    ])->toArray();

    expect($node)->toBe([
        'type' => 'grid',
        'columns' => 2,
        'schema' => [
            [
                'type' => 'text_input',
                'name' => 'title',
                'label' => 'Title',
            ],
            [
                'type' => 'select',
                'name' => 'status',
                'label' => 'Status',
            ],
        ],
    ]);
});

it('defaults to two columns', function () {
    $node = Grid::make()->toArray();

    expect($node['columns'])->toBe(2);
});

it('clamps columns to a minimum of one', function () {
    expect(Grid::make()->columns(0)->getColumns())->toBe(1);
    expect(Grid::make()->columns(3)->getColumns())->toBe(3);
});

it('clamps columnSpan to the supported 1-12 grid domain', function () {
    expect(TextInput::make('title')->columnSpan(0)->toArray()['columnSpan'])->toBe(1);
    expect(TextInput::make('title')->columnSpan(13)->toArray()['columnSpan'])->toBe(12);
    expect(TextInput::make('title')->columnSpan(4)->toArray()['columnSpan'])->toBe(4);
});

it('appends components through repeated schema calls', function () {
    $grid = Grid::make()->schema([TextInput::make('title')])->schema([Select::make('status')]);

    expect($grid->getChildComponents())->toHaveCount(2);
});

it('serializes nested grids recursively', function () {
    $node = Grid::make()->schema([
        Grid::make()->columns(3)->schema([TextInput::make('nested')]),
    ])->toArray();

    expect($node['schema'][0]['type'])->toBe('grid');
    expect($node['schema'][0]['columns'])->toBe(3);
    expect($node['schema'][0]['schema'][0]['name'])->toBe('nested');
});

it('finds components nested inside layouts by name', function () {
    $schema = Schema::make()->components([
        Grid::make()->schema([
            Grid::make()->schema([Select::make('state')]),
        ]),
    ]);

    $state = $schema->getComponentByName('state');

    expect($state)->toBeInstanceOf(Select::class);
    expect($schema->getComponentByName('missing'))->toBeNull();
});
