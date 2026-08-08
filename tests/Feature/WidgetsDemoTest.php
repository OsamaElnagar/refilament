<?php

declare(strict_types=1);

use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('serves the widgets demo page as an Inertia page', function () {
    Post::factory()->count(5)->create();
    User::factory()->count(3)->create();

    $response = $this->get('/refilament/widgets', ['X-Inertia' => 'true']);

    $response->assertOk();
    $response->assertJsonPath('component', 'refilament/widgets-overview');
    $response->assertJsonPath('props.widget.type', 'stats_overview');
    $response->assertJsonPath('props.widget.heading', 'Content overview');
    $response->assertJsonPath('props.widget.description', 'A live snapshot of the workbench database');
    $response->assertJsonPath('props.widget.columns', 4);
    $response->assertJsonCount(3, 'props.widget.stats');

    // First stat — total posts (closure resolved to the count at serialization).
    $response->assertJsonPath('props.widget.stats.0.label', 'Total posts');
    $response->assertJsonPath('props.widget.stats.0.value', 5);
    $response->assertJsonPath('props.widget.stats.0.icon', 'tag');
    $response->assertJsonPath('props.widget.stats.0.color', 'primary');

    // Second stat — published posts, with a description.
    $response->assertJsonPath('props.widget.stats.1.label', 'Published posts');
    $response->assertJsonPath('props.widget.stats.1.description', 'with a published date');
    $response->assertJsonPath('props.widget.stats.1.color', 'success');

    // Third stat — total users (the Post factory also creates a User per
    // post, so assert against the real count rather than a hardcoded 3).
    $response->assertJsonPath('props.widget.stats.2.label', 'Total users');
    $response->assertJsonPath('props.widget.stats.2.value', User::query()->count());
    $response->assertJsonPath('props.widget.stats.2.icon', 'users');
});
