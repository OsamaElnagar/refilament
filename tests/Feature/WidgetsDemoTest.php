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
    $response->assertJsonCount(4, 'props.widgets');

    // Widget 0 — the stats overview.
    $response->assertJsonPath('props.widgets.0.type', 'stats_overview');
    $response->assertJsonPath('props.widgets.0.heading', 'Content overview');
    $response->assertJsonPath('props.widgets.0.description', 'A live snapshot of the workbench database');
    $response->assertJsonPath('props.widgets.0.columns', 4);
    $response->assertJsonCount(3, 'props.widgets.0.stats');

    // First stat — total posts (closure resolved to the count at serialization).
    $response->assertJsonPath('props.widgets.0.stats.0.label', 'Total posts');
    $response->assertJsonPath('props.widgets.0.stats.0.value', 5);
    $response->assertJsonPath('props.widgets.0.stats.0.icon', 'tag');
    $response->assertJsonPath('props.widgets.0.stats.0.color', 'primary');

    // Second stat — published posts, with a description.
    $response->assertJsonPath('props.widgets.0.stats.1.label', 'Published posts');
    $response->assertJsonPath('props.widgets.0.stats.1.description', 'with a published date');
    $response->assertJsonPath('props.widgets.0.stats.1.color', 'success');

    // Third stat — total users (the Post factory also creates a User per
    // post, so assert against the real count rather than a hardcoded 3).
    $response->assertJsonPath('props.widgets.0.stats.2.label', 'Total users');
    $response->assertJsonPath('props.widgets.0.stats.2.value', User::query()->count());
    $response->assertJsonPath('props.widgets.0.stats.2.icon', 'users');

    // Widget 1 — a bar chart wired on the posts' status column.
    $response->assertJsonPath('props.widgets.1.type', 'chart_bar');
    $response->assertJsonPath('props.widgets.1.heading', 'Posts by status');
    $response->assertJsonMissingPath('props.widgets.1.color');
    $response->assertJsonCount(3, 'props.widgets.1.data.labels');
    $response->assertJsonCount(1, 'props.widgets.1.data.datasets');

    // Widget 2 — a pie chart wired on the posts' author column. Its labels are
    // the distinct (up to 4) seeded authors, so assert shape rather than exact
    // counts: one dataset, with a data point per label.
    $response->assertJsonPath('props.widgets.2.type', 'chart_pie');
    $response->assertJsonPath('props.widgets.2.heading', 'Posts per author');
    $response->assertJsonCount(1, 'props.widgets.2.data.datasets');

    $widgets = $response->json('props.widgets');
    $labels = $widgets[2]['data']['labels'];
    $totals = $widgets[2]['data']['datasets'][0]['data'];
    test()->assertCount(count($labels), $totals);

    // Widget 3 — a table widget (slice D1): a widget that is itself a table.
    // Its node embeds the table's first page (5 of the 5 seeded posts, sorted
    // by created_at desc); interactions reuse the typed table endpoint.
    $response->assertJsonPath('props.widgets.3.type', 'table');
    $response->assertJsonPath('props.widgets.3.heading', 'Recent posts');
    $response->assertJsonPath('props.widgets.3.table.id', 'recent-posts-table');
    $response->assertJsonCount(5, 'props.widgets.3.table.columns');
    $response->assertJsonCount(5, 'props.widgets.3.table.rows');

    $titles = collect($response->json('props.widgets.3.table.rows'))->pluck('title')->all();
    // The table's default sort is `created_at desc` with the deterministic id
    // tiebreaker — mirror it exactly when comparing against the DB.
    $latest = Post::query()->latest('created_at')->orderByDesc('id')->limit(5)->pluck('title')->all();
    expect($titles)->toBe($latest);
});
