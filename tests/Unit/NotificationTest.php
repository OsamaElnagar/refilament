<?php

declare(strict_types=1);

use Refilament\Refilament\Notifications\Notification;

it('serializes only the title by default', function () {
    expect(Notification::make()->title('Saved.')->toArray())
        ->toBe(['title' => 'Saved.']);
});

it('omits unset fields', function () {
    expect(Notification::make()->toArray())
        ->toBe(['title' => '']);
});

it('serializes the body when set', function () {
    expect(Notification::make()->title('Saved.')->body('Your post is live.')->toArray())
        ->toBe([
            'title' => 'Saved.',
            'body' => 'Your post is live.',
        ]);
});

it('serializes the status and its helpers', function () {
    expect(Notification::make()->status('danger')->toArray()['status'])->toBe('danger');
    expect(Notification::make()->success()->toArray()['status'])->toBe('success');
    expect(Notification::make()->info()->toArray()['status'])->toBe('info');
    expect(Notification::make()->warning()->toArray()['status'])->toBe('warning');
});

it('serializes the icon when set', function () {
    expect(Notification::make()->title('Saved.')->icon('heroicon-o-check')->toArray()['icon'])
        ->toBe('heroicon-o-check');
});

it('serializes a numeric duration and its seconds helper', function () {
    expect(Notification::make()->title('Saved.')->duration(3000)->toArray()['duration'])->toBe(3000);
    expect(Notification::make()->title('Saved.')->seconds(1.5)->toArray()['duration'])->toBe(1500);
});

it('serializes the persistent duration', function () {
    expect(Notification::make()->title('Saved.')->persistent()->toArray()['duration'])
        ->toBe('persistent');
});

it('builds the additive response keys', function () {
    $notification = Notification::make()->title('Posts deleted.')->success();

    expect(Notification::toResponseArray($notification, 'Selected posts deleted.'))->toBe([
        'notification' => [
            'title' => 'Posts deleted.',
            'status' => 'success',
        ],
        'message' => 'Selected posts deleted.',
    ]);
});

it('keeps the plain message when no notification is set', function () {
    expect(Notification::toResponseArray(null, 'Saved.'))
        ->toBe(['message' => 'Saved.']);
});

it('returns an empty array when nothing is set', function () {
    expect(Notification::toResponseArray(null, null))->toBe([]);
});
