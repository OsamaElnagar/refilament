<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Workbench\App\Models\User;

it('returns an empty payload for an unauthenticated visitor', function () {
    $this->getJson('/refilament/notifications')
        ->assertOk()
        ->assertJson(['unread' => 0, 'notifications' => []]);
});

it('lists the authenticated user notifications with the unread count', function () {
    $user = User::factory()->create();

    $user->notifications()->createMany([
        [
            'id' => Str::uuid(),
            'type' => 'demo',
            'data' => ['title' => 'Published', 'url' => '/refilament/posts'],
            'read_at' => now(),
            'created_at' => now()->subHour(),
        ],
        [
            'id' => Str::uuid(),
            'type' => 'demo',
            'data' => ['title' => 'Welcome', 'body' => 'Hello there'],
            'read_at' => null,
            'created_at' => now(),
        ],
    ]);

    $this->actingAs($user)->getJson('/refilament/notifications')
        ->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonCount(2, 'notifications')
        // Latest first — the unread welcome sits on top.
        ->assertJsonPath('notifications.0.title', 'Welcome')
        ->assertJsonPath('notifications.0.body', 'Hello there')
        ->assertJsonPath('notifications.0.readAt', null)
        ->assertJsonPath('notifications.1.title', 'Published')
        ->assertJsonPath('notifications.1.url', '/refilament/posts')
        ->assertJsonPath('notifications.1.readAt', fn (mixed $value) => is_string($value));
});

it('marks a single notification read', function () {
    $user = User::factory()->create();

    $notification = $user->notifications()->create([
        'id' => Str::uuid(),
        'type' => 'demo',
        'data' => ['title' => 'Welcome'],
    ]);

    $this->actingAs($user)->postJson('/refilament/notifications/'.$notification->id.'/read')
        ->assertOk()
        ->assertJsonPath('unread', 0);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks every notification read', function () {
    $user = User::factory()->create();

    $user->notifications()->createMany([
        ['id' => Str::uuid(), 'type' => 'demo', 'data' => ['title' => 'One']],
        ['id' => Str::uuid(), 'type' => 'demo', 'data' => ['title' => 'Two']],
    ]);

    $this->actingAs($user)->postJson('/refilament/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('unread', 0);

    expect($user->unreadNotifications()->count())->toBe(0);
});
