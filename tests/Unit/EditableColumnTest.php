<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Column;
use Workbench\App\Models\Comment;

it('is not editable by default', function () {
    expect(Column::make('is_visible')->isEditable())->toBeFalse();
});

it('becomes editable via editable() and serializes the flag', function () {
    $column = Column::make('is_visible')->editable();

    expect($column->isEditable())->toBeTrue();
    expect($column->toArray()['editable'])->toBeTrue();
});

it('does not serialize the editable flag when not editable', function () {
    expect(Column::make('is_visible')->toArray())->not->toHaveKey('editable');
});

it('authorizes inline edits via canEdit()', function () {
    $allowed = Comment::factory()->create(['is_visible' => true]);
    $denied = Comment::factory()->create(['is_visible' => true]);

    $column = Column::make('is_visible')
        ->editable()
        ->canEdit(fn (Comment $record): bool => $record->id === $allowed->id);

    expect($column->isAuthorizedFor($allowed))->toBeTrue();
    expect($column->isAuthorizedFor($denied))->toBeFalse();
});

it('authorizes inline edits by default when no canEdit() is set', function () {
    $comment = Comment::factory()->create(['is_visible' => true]);

    expect(Column::make('is_visible')->editable()->isAuthorizedFor($comment))->toBeTrue();
});

it('stores server-side validation rules as an array', function () {
    $column = Column::make('is_visible')->rules('boolean');

    expect($column->getEditRules())->toBe(['boolean']);

    $column = Column::make('is_visible')->rules(['boolean', 'nullable']);

    expect($column->getEditRules())->toBe(['boolean', 'nullable']);
});

it('persists an edited value by mass-assigning to the named attribute', function () {
    $comment = Comment::factory()->create(['is_visible' => false]);

    Column::make('is_visible')->editable()->updateState($comment, true);

    expect($comment->fresh()->is_visible)->toBeTrue();
});

it('persists an edited value through a custom updateStateUsing() handler', function () {
    $comment = Comment::factory()->create(['is_visible' => false]);

    $column = Column::make('is_visible')
        ->editable()
        ->updateStateUsing(function (Comment $record, mixed $state): void {
            $record->forceFill(['is_visible' => $state === true])->save();
        });

    $column->updateState($comment, true);

    expect($comment->fresh()->is_visible)->toBeTrue();
});
