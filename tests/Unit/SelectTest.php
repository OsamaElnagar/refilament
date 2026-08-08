<?php

declare(strict_types=1);

use LogicException;
use Refilament\Refilament\Schemas\Components\Select;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('serializes a plain select with static options', function () {
    $node = Select::make('status')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ])
        ->toArray();

    expect($node)->toBe([
        'type' => 'select',
        'name' => 'status',
        'label' => 'Status',
        'options' => [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'published', 'label' => 'Published'],
        ],
    ]);
});

it('serializes a searchable select', function () {
    $node = Select::make('country')->searchable()->options(['us' => 'United States'])->toArray();

    expect($node['searchable'])->toBeTrue();
});

it('serializes a multiple select', function () {
    $node = Select::make('tags')->multiple()->options(['a' => 'A', 'b' => 'B'])->toArray();

    expect($node['multiple'])->toBeTrue();
});

it('omits the flags when not set', function () {
    $node = Select::make('status')->toArray();

    expect($node)->not->toHaveKey('multiple');
    expect($node)->not->toHaveKey('searchable');
});

it('inherits placeholder from the base component', function () {
    $node = Select::make('status')->placeholder('Pick one')->toArray();

    expect($node['placeholder'])->toBe('Pick one');
});

it('serializes dependsOn', function () {
    $node = Select::make('state')->dependsOn(['country'])->toArray();

    expect($node['dependsOn'])->toBe(['country']);
});

it('omits static options when an options resolver is registered', function () {
    $node = Select::make('state')
        ->options(['x' => 'Static'])
        ->dependsOn(['country'])
        ->resolveOptionsUsing(fn (array $data): array => [])
        ->toArray();

    expect($node)->toHaveKey('dependsOn');
    expect($node)->not->toHaveKey('options');
});

it('never serializes the options resolver closure', function () {
    $node = Select::make('state')
        ->dependsOn(['country'])
        ->resolveOptionsUsing(fn (array $data): array => ['x' => 'X'])
        ->toArray();

    expect($node)->not->toHaveKey('optionsResolver');
});

it('requires dependsOn when an options resolver is registered', function () {
    Select::make('state')
        ->resolveOptionsUsing(fn (array $data): array => [])
        ->toArray();
})->throws(LogicException::class, 'must declare [dependsOn()]');

it('resolves options to the contract shape', function () {
    $field = Select::make('state')
        ->dependsOn(['country'])
        ->resolveOptionsUsing(fn (array $data): array => $data['country'] === 'us'
            ? ['al' => 'Alabama', 'ca' => 'California']
            : []);

    expect($field->resolveOptions(['country' => 'us']))->toBe([
        ['value' => 'al', 'label' => 'Alabama'],
        ['value' => 'ca', 'label' => 'California'],
    ]);

    expect($field->resolveOptions(['country' => 'gb']))->toBe([]);
});

it('reports whether an options resolver is registered', function () {
    $plain = Select::make('state');
    $resolved = Select::make('state')->resolveOptionsUsing(fn (array $data): array => []);

    expect($plain->hasOptionsResolver())->toBeFalse();
    expect($plain->resolveOptions([]))->toBe([]);
    expect($resolved->hasOptionsResolver())->toBeTrue();
});

it('derives options from a backed enum class', function () {
    $node = Select::make('status')->options(DemoStatus::class)->toArray();

    expect($node['options'])->toBe([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published'],
    ]);
});

it('uses the enum getLabel() when it declares one', function () {
    $node = Select::make('status')->options(DemoStatusWithLabel::class)->toArray();

    expect($node['options'])->toBe([
        ['value' => 'draft', 'label' => 'Draft copy'],
        ['value' => 'published', 'label' => 'Live'],
    ]);
});

it('accepts an enum instance as options', function () {
    $node = Select::make('status')->options(DemoStatus::Draft)->toArray();

    expect($node['options'])->toBe([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published'],
    ]);
});

it('rejects a non-backed enum in options()', function () {
    expect(fn () => Select::make('status')->options(DemoUnitEnum::class))->toThrow(LogicException::class);
});

it('resolves relationship options at serialization', function () {
    User::factory()->create(['name' => 'Ada Lovelace']);

    $node = Select::make('user_id')
        ->label('User')
        ->relationship('user', 'name')
        ->model(Post::class)
        ->toArray();

    expect($node['options'])->toHaveCount(1);
    expect($node['options'][0]['label'])->toBe('Ada Lovelace');
});

it('uses getOptionLabelFromRecordUsing for relationship labels', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $node = Select::make('user_id')
        ->relationship('user', 'name')
        ->model(Post::class)
        ->getOptionLabelFromRecordUsing(fn (User $record): string => "User #{$record->getKey()}")
        ->toArray();

    expect($node['options'][0]['label'])->toBe("User #{$user->getKey()}");
});

it('fails fast when a relationship select has no model', function () {
    expect(fn () => Select::make('user_id')->relationship('user', 'name')->toArray())
        ->toThrow(LogicException::class);
});

it('rejects combining relationship() with static options', function () {
    expect(fn () => Select::make('user_id')
        ->relationship('user', 'name')
        ->model(Post::class)
        ->options(['1' => 'One'])
        ->toArray())->toThrow(LogicException::class);
});

enum DemoStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

enum DemoStatusWithLabel: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft copy',
            self::Published => 'Live',
        };
    }
}

enum DemoUnitEnum
{
    case Draft;
}
