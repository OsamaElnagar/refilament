<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Refilament\Refilament\Support\Enums\Alignment;
use Refilament\Refilament\Support\Enums\FontFamily;
use Refilament\Refilament\Support\Enums\FontWeight;
use Refilament\Refilament\Support\Enums\IconPosition;
use Refilament\Refilament\Support\Enums\IconSize;
use Refilament\Refilament\Tables\Column;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('resolves the label from the column name', function () {
    expect(Column::make('published_at')->getLabel())->toBe('Published At');
});

it('resolves the label from the last dot-notation segment', function () {
    expect(Column::make('user.name')->getLabel())->toBe('Name');
});

it('prefers an explicit label over the derived one', function () {
    expect(Column::make('user.name')->label('Author')->getLabel())->toBe('Author');
});

it('formats money with the given currency and divisor', function () {
    $post = Post::factory()->create(['views' => 12345]);

    $cell = Column::make('views')->money('USD', 100)->getStateFor($post);

    expect($cell)->toBe('$123.45');
});

it('formats a raw number with grouped thousands', function () {
    $post = Post::factory()->create(['views' => 1234567]);

    $cell = Column::make('views')->numeric()->getStateFor($post);

    expect($cell)->toBe('1,234,567');
});

it('formats a date', function () {
    $post = Post::factory()->create(['published_at' => Carbon::parse('2026-01-15 10:30:00')]);

    $cell = Column::make('published_at')->date('Y-m-d')->getStateFor($post);

    expect($cell)->toBe('2026-01-15');
});

it('formats a date and time', function () {
    $post = Post::factory()->create(['published_at' => Carbon::parse('2026-01-15 10:30:00')]);

    $cell = Column::make('published_at')->dateTime('Y-m-d H:i')->getStateFor($post);

    expect($cell)->toBe('2026-01-15 10:30');
});

it('formats a time only', function () {
    $post = Post::factory()->create(['published_at' => Carbon::parse('2026-01-15 10:30:00')]);

    $cell = Column::make('published_at')->time('H:i')->getStateFor($post);

    expect($cell)->toBe('10:30');
});

it('truncates a value to a character limit', function () {
    $post = Post::factory()->create(['title' => 'A dramatically long post title that should get truncated for display']);

    $cell = Column::make('title')->limit(10)->getStateFor($post);

    expect($cell)->toBe('A dramatic...');
});

it('prepends and appends static text', function () {
    $post = Post::factory()->create(['views' => 5]);

    $cell = Column::make('views')->prefix('+')->suffix('%')->getStateFor($post);

    expect($cell)->toBe('+5%');
});

it('formats a null state as null for money and numeric', function () {
    expect(
        Column::make('views')->money()->getStateFor(Post::factory()->make(['views' => null])),
    )->toBeNull();
    expect(
        Column::make('views')->numeric()->getStateFor(Post::factory()->make(['views' => null])),
    )->toBeNull();
});

it('serializes a plain column as a scalar cell', function () {
    $post = Post::factory()->create(['views' => 3]);

    expect(Column::make('views')->serializeCell($post))->toBe(3);
});

it('serializes a badge column to a structured cell with a color', function () {
    $post = Post::factory()->create(['status' => 'published']);

    $column = Column::make('status')
        ->badge()
        ->color(fn (string $state): string => $state === 'published' ? 'success' : 'secondary');

    expect($column->serializeCell($post))->toBe([
        'value' => 'published',
        'badge' => true,
        'color' => 'success',
    ]);
});

it('resolves a per-record color for the badge', function () {
    $column = Column::make('status')
        ->badge()
        ->color(fn (string $state): string => $state === 'draft' ? 'warning' : 'secondary');

    $draft = Post::factory()->create(['status' => 'draft']);
    $published = Post::factory()->create(['status' => 'published']);

    expect($column->serializeCell($draft)['color'])->toBe('warning');
    expect($column->serializeCell($published)['color'])->toBe('secondary');
});

it('colors a column from a static color', function () {
    $post = Post::factory()->create(['status' => 'draft']);

    $cell = Column::make('status')->color('danger')->serializeCell($post);

    expect($cell['color'])->toBe('danger');
    expect($cell)->toHaveKey('value', 'draft');
});

it('maps states to colors via colors()', function () {
    $column = Column::make('status')->colors([
        'success' => 'published',
        'warning' => 'draft',
    ]);

    $published = Post::factory()->create(['status' => 'published']);
    $draft = Post::factory()->create(['status' => 'draft']);

    expect($column->serializeCell($published)['color'])->toBe('success');
    expect($column->serializeCell($draft)['color'])->toBe('warning');
});

it('resolves an icon and icon color', function () {
    $post = Post::factory()->create(['status' => 'published']);

    $column = Column::make('status')
        ->badge()
        ->icon('check')
        ->iconColor('success');

    expect($column->serializeCell($post))->toHaveKey('icon', 'check');
    expect($column->serializeCell($post))->toHaveKey('iconColor', 'success');
});

it('wraps a cell in a url', function () {
    $column = Column::make('title')->url('/posts/{record}');

    $post = Post::factory()->create(['title' => 'My Post']);

    $cell = $column->serializeCell($post);

    expect($cell['url'])->toBe('/posts/'.$post->getKey());
    expect($cell['value'])->toBe('My Post');
});

it('marks a cell url as open-in-new-tab', function () {
    $column = Column::make('title')->url('/x')->openUrlInNewTab();

    expect($column->serializeCell(Post::factory()->create())['openUrlInNewTab'])->toBeTrue();
});

it('uses a state resolver for related attributes', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $post = Post::factory()->create(['user_id' => $user->id]);

    $column = Column::make('title')
        ->getStateUsing(static fn (Post $record): ?string => $record->user?->name);

    expect($column->getStateFor($post))->toBe('Ada Lovelace');
});

it('emits nothing extra on the definition for a plain column', function () {
    $payload = Column::make('title')->toArray();

    expect($payload)->toBe(['name' => 'title', 'label' => 'Title']);
});

it('emits a badge flag on the definition', function () {
    $payload = Column::make('status')->badge()->toArray();

    expect($payload['badge'])->toBeTrue();
});

it('emits url flags on the definition', function () {
    $payload = Column::make('title')->url('/posts/{record}')->openUrlInNewTab()->toArray();

    expect($payload['url'])->toBeTrue();
    expect($payload['openUrlInNewTab'])->toBeTrue();
});

it('emits a tooltip on the definition', function () {
    $payload = Column::make('title')->tooltip('Row title')->toArray();

    expect($payload['tooltip'])->toBe('Row title');
});

it('emits alignment as its enum value', function () {
    $payload = Column::make('title')->alignment(Alignment::Center)->toArray();

    expect($payload['alignment'])->toBe('center');
});

it('emits a pixel width', function () {
    $payload = Column::make('title')->width(220)->toArray();

    expect($payload['width'])->toBe('220px');
});

it('emits weight, font family and line clamp', function () {
    $payload = Column::make('title')
        ->weight(FontWeight::Bold)
        ->fontFamily(FontFamily::Mono)
        ->lineClamp(2)
        ->toArray();

    expect($payload['weight'])->toBe('bold');
    expect($payload['fontFamily'])->toBe('mono');
    expect($payload['lineClamp'])->toBe(2);
});

it('emits icon size and position when set', function () {
    $payload = Column::make('title')
        ->iconSize(IconSize::Large)
        ->iconPosition(IconPosition::After)
        ->toArray();

    expect($payload['iconSize'])->toBe('lg');
    expect($payload['iconPosition'])->toBe('after');
});

it('omits icon position when not configured', function () {
    $payload = Column::make('title')->toArray();

    expect($payload)->not()->toHaveKey('iconPosition');
});

it('emits extra attributes', function () {
    $payload = Column::make('title')->extraAttributes(['data-testid' => 'title-cell'])->toArray();

    expect($payload['extraAttributes'])->toBe(['data-testid' => 'title-cell']);
});

it('resolves presentation closures at serialization time', function () {
    $payload = Column::make('title')
        ->tooltip(fn (): string => 'Resolved')
        ->alignment(fn (): string => Alignment::End->value)
        ->weight(fn (): string => FontWeight::SemiBold->value)
        ->toArray();

    expect($payload['tooltip'])->toBe('Resolved');
    expect($payload['alignment'])->toBe('end');
    expect($payload['weight'])->toBe('semibold');
});

it('never serializes formatter or resolver closures', function () {
    $payload = Column::make('views')
        ->getStateUsing(static fn (mixed $record): mixed => $record)
        ->money()
        ->toArray();

    expect($payload)->not()->toHaveKey('getStateUsing');
    expect($payload)->not()->toHaveKey('formatStateUsing');
});

it('serializes the state through formatStateUsing', function () {
    $post = Post::factory()->create(['views' => 4]);

    $column = Column::make('views')->formatStateUsing(
        static fn (int $state): string => $state > 3 ? 'high' : 'low',
    );

    expect($column->getStateFor($post))->toBe('high');
});

it('serializes a static placeholder', function () {
    $payload = Column::make('title')->placeholder('Untitled')->toArray();

    expect($payload['placeholder'])->toBe('Untitled');
});

it('evaluates a closure placeholder at serialization', function () {
    $payload = Column::make('title')
        ->placeholder(fn (): string => 'Untitled')
        ->toArray();

    expect($payload['placeholder'])->toBe('Untitled');
});

it('omits the placeholder key when not configured', function () {
    expect(Column::make('title')->toArray())->not()->toHaveKey('placeholder');
});

it('emits the expandable flags on the definition', function () {
    $payload = Column::make('content')->expandable()->toArray();

    expect($payload['expandable'])->toBeTrue();
    expect($payload['expandableLines'])->toBe(2);
});

it('emits a custom expandable line count', function () {
    $payload = Column::make('content')->expandable(5)->toArray();

    expect($payload['expandable'])->toBeTrue();
    expect($payload['expandableLines'])->toBe(5);
});

it('omits expandableLines when the column is not expandable', function () {
    expect(Column::make('content')->toArray())->not()->toHaveKey('expandable');
    expect(Column::make('content')->toArray())->not()->toHaveKey('expandableLines');
});

it('emits previewable, wrap and copyable flags', function () {
    $payload = Column::make('content')
        ->previewOnClick()
        ->wrap()
        ->copyable()
        ->toArray();

    expect($payload['previewable'])->toBeTrue();
    expect($payload['wrap'])->toBeTrue();
    expect($payload['copyable'])->toBeTrue();
});

it('does not emit rich-text flags when unset', function () {
    $payload = Column::make('content')->toArray();

    expect($payload)->not()->toHaveKey('previewable');
    expect($payload)->not()->toHaveKey('wrap');
    expect($payload)->not()->toHaveKey('copyable');
});
