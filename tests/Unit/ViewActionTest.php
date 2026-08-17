<?php

declare(strict_types=1);

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Actions\ViewAction;

it('ships Filament-mirroring view defaults', function () {
    $action = ViewAction::make();

    expect($action->getName())->toBe('view');
    expect($action->getLabel())->toBe('View');
    expect($action->getIcon())->toBe('eye');
    expect($action->getColor())->toBe('primary');
    expect($action->getUrlPage())->toBe('view');
});

it('serializes the definition without a url — the page URL is per-record', function () {
    $payload = ViewAction::make()->toArray();

    expect($payload['name'])->toBe('view');
    expect($payload['label'])->toBe('View');
    expect($payload['icon'])->toBe('eye');
    expect($payload['color'])->toBe('primary');
    expect($payload)->not->toHaveKey('url');
});

it('lets a consumer override the defaults', function () {
    $action = ViewAction::make()
        ->label('Open')
        ->icon('arrow-up-right');

    expect($action->getLabel())->toBe('Open');
    expect($action->getIcon())->toBe('arrow-up-right');
});

it('resolves a static url string and a per-record closure', function () {
    $static = Action::make('go')->url('/somewhere');
    expect($static->resolveUrl())->toBe('/somewhere');

    $closure = Action::make('go')->url(fn ($record): string => "/records/{$record->id}");
    expect($closure->resolveUrl(null))->toBeNull();
    expect($closure->resolveUrl((object) ['id' => 7]))->toBe('/records/7');
    // Closures never serialize on the definition — they resolve per row.
    expect($closure->toArray())->not->toHaveKey('url');
});
