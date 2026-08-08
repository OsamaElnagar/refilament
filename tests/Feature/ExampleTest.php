<?php

declare(strict_types=1);

use Refilament\Refilament\Refilament;

it('resolves the singleton', function () {
    expect(app(Refilament::class))->toBeInstanceOf(Refilament::class);
});

it('returns the same instance from the container', function () {
    expect(app(Refilament::class))->toBe(app(Refilament::class));
});

it('merges the package config', function () {
    expect(config('refilament.placeholder'))->toBe('default');
});

it('loads the package translations', function () {
    expect(trans('refilament::messages.placeholder'))->toBe('Refilament placeholder translation.');
});

it('loads the package views', function () {
    expect(view()->exists('refilament::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('refilament:placeholder')
        ->expectsOutputToContain('Refilament placeholder command executed.')
        ->assertSuccessful();
});
