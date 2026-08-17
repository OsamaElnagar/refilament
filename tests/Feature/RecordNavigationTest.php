<?php

declare(strict_types=1);

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Actions\ViewAction;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;
use Workbench\App\Refilament\Resources\PostResource;

it('resolves each row click target to the view page by default', function () {
    $post = Post::factory()->create();

    $response = $this->getJson('/refilament/table/posts?perPage=50');

    $response->assertOk();
    $row = collect($response->json('rows'))->firstWhere('id', $post->id);

    expect($row['recordUrl'])->toBe(route('refilament.resource.view', [
        'resource' => 'posts',
        'record' => $post->id,
    ]));
});

it('resolves ViewAction URLs per record and emits the row actionUrls', function () {
    $post = Post::factory()->create();

    $table = Table::make()
        ->id('record-navigation-test')
        ->urlUsing(static fn (string $page, mixed $record): ?string => match ($page) {
            'default', 'view' => "/posts/{$record->getKey()}/view",
            default => null,
        })
        ->actions([
            ViewAction::make(),
            Action::make('delete')->label('Delete'),
        ])
        ->columns([Column::make('id')->label('ID')])
        ->query(Post::query());

    $row = $table->toPayload()['rows'][0];

    expect($row['actions'])->toBe(['view', 'delete']);
    expect($row['actionUrls']['view']['url'])->toBe("/posts/{$row['id']}/view");
    expect($row['actionUrls']['view'])->not->toHaveKey('openUrlInNewTab');
    expect($row['recordUrl'])->toBe("/posts/{$row['id']}/view");
    expect($row)->not->toHaveKey('actionUrls.delete');
});

it('drops a page-navigation action whose page resolves no URL', function () {
    $post = Post::factory()->create();

    $table = Table::make()
        ->id('record-navigation-none')
        ->urlUsing(static fn (string $page, mixed $record): ?string => null)
        ->actions([
            ViewAction::make(),
            Action::make('delete')->label('Delete'),
        ])
        ->columns([Column::make('id')->label('ID')])
        ->query(Post::query());

    $row = $table->toPayload()['rows'][0];

    // The view action can't navigate anywhere — it never renders.
    expect($row['actions'])->toBe(['delete']);
    expect($row)->not->toHaveKey('recordUrl');
    expect($row)->not->toHaveKey('actionUrls');
});

it('resolves a closure url per record into actionUrls', function () {
    $post = Post::factory()->create();

    $table = Table::make()
        ->id('closure-url-test')
        ->actions([
            Action::make('open')->label('Open')->url(fn ($record): string => "/open/{$record->getKey()}"),
        ])
        ->columns([Column::make('id')->label('ID')])
        ->query(Post::query());

    $row = $table->toPayload()['rows'][0];

    expect($row['actions'])->toBe(['open']);
    expect($row['actionUrls']['open']['url'])->toBe("/open/{$row['id']}");
});

it('drops a closure-URL action that resolves no URL for the record', function () {
    $post = Post::factory()->create();

    $table = Table::make()
        ->id('closure-url-none')
        ->actions([
            // A closure returning null means "no URL for this record" — the
            // action must behave like an unresolvable page-navigation action
            // and never render (it can't navigate, and it has no server
            // behavior to fall back to).
            Action::make('open')->label('Open')->url(fn ($record): ?string => null),
            Action::make('delete')->label('Delete'),
        ])
        ->columns([Column::make('id')->label('ID')])
        ->query(Post::query());

    $row = $table->toPayload()['rows'][0];

    expect($row['actions'])->toBe(['delete']);
    expect($row)->not->toHaveKey('actionUrls');
});

it('prefers the view page over the edit page for the default record URL', function () {
    $post = Post::factory()->create();

    expect(PostResource::getRecordUrl('default', $post))->toBe(
        route('refilament.resource.view', ['resource' => 'posts', 'record' => $post->id]),
    );
    expect(PostResource::getRecordUrl('view', $post))->toBe(
        route('refilament.resource.view', ['resource' => 'posts', 'record' => $post->id]),
    );
    expect(PostResource::getRecordUrl('edit', $post))->toBe(
        route('refilament.resource.edit', ['resource' => 'posts', 'record' => $post->id]),
    );
    expect(PostResource::getRecordUrl('nope', $post))->toBeNull();
});
