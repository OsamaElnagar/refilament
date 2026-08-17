<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Columns\SelectColumn;
use Workbench\App\Models\Post;

it('is editable by default and serializes the editable flag', function () {
    $column = SelectColumn::make('status');

    expect($column->isEditable())->toBeTrue();
    expect($column->toArray()['editable'])->toBeTrue();
    expect($column->toArray()['kind'])->toBe('select');
});

it('serializes the options as value/label pairs', function () {
    $column = SelectColumn::make('status')->options([
        'draft' => 'Draft',
        'published' => 'Published',
    ]);

    expect($column->toArray()['options'])->toBe([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published'],
    ]);
});

it('marks disabled options in the serialized list', function () {
    $column = SelectColumn::make('status')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ])
        ->disabledOption('published');

    expect($column->toArray()['options'])->toBe([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published', 'isDisabled' => true],
    ]);
});

it('serializes a placeholder', function () {
    $column = SelectColumn::make('status')->placeholder('Choose…');

    expect($column->toArray()['placeholder'])->toBe('Choose…');
});

it('omits the placeholder key when not set', function () {
    expect(SelectColumn::make('status')->toArray())->not->toHaveKey('placeholder');
});

it('resolves options from a closure', function () {
    $column = SelectColumn::make('status')->options(fn (): array => ['a' => 'A', 'b' => 'B']);

    expect($column->getOptions())->toBe(['a' => 'A', 'b' => 'B']);
});

it('ships the raw value while editable', function () {
    $post = Post::factory()->create(['status' => 'archived']);

    expect(SelectColumn::make('status')->options([])->serializeCell($post))->toBe('archived');
});

it('ships the option label when not editable', function () {
    $post = Post::factory()->create(['status' => 'published']);

    $column = SelectColumn::make('status')
        ->editable(false)
        ->options(['draft' => 'Draft', 'published' => 'Published']);

    expect($column->serializeCell($post))->toBe('Published');
});

it('falls back to the raw value when not editable and the label is unknown', function () {
    $post = Post::factory()->create(['status' => 'weird']);

    $column = SelectColumn::make('status')
        ->editable(false)
        ->options(['draft' => 'Draft']);

    expect($column->serializeCell($post))->toBe('weird');
});
