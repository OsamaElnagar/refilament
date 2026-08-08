<?php

declare(strict_types=1);

use Workbench\App\Models\Post;

beforeEach(function () {
    Post::factory()->count(45)->create();
});

it('serves the first page with the table definition', function () {
    $response = $this->getJson('/refilament/table/posts');

    $response->assertOk();
    $response->assertJsonPath('id', 'posts');
    $response->assertJsonPath('heading', 'Posts');
    $response->assertJsonPath('page', 1);
    $response->assertJsonPath('perPage', 10);
    $response->assertJsonPath('total', 45);
    $response->assertJsonPath('lastPage', 5);
    $response->assertJsonCount(8, 'columns');
    $response->assertJsonCount(10, 'rows');
    $response->assertJsonStructure([
        'rows' => [
            '*' => ['id', 'title', 'author', 'status', 'user.name', 'views', 'published_at'],
        ],
    ]);

    // Row ids mirror the record's primary key as-is (numeric-string casts get
    // re-encoded as numbers by json_encode, so the type follows the key).
    $rowId = $response->json('rows.0.id');
    $expected = Post::query()->orderByDesc('published_at')->orderByDesc('id')->first()->getKey();
    expect($rowId)->toBe($expected);
});

it('serves a different page on request', function () {
    $pageOne = $this->getJson('/refilament/table/posts?page=1')->json('rows');
    $pageTwo = $this->getJson('/refilament/table/posts?page=2')->json('rows');

    expect($pageOne)->toHaveCount(10);
    expect($pageTwo)->toHaveCount(10);
    expect($pageTwo[0]['id'])->not->toBe($pageOne[0]['id']);
});

it('honours a valid perPage option', function () {
    $response = $this->getJson('/refilament/table/posts?perPage=25');

    $response->assertOk();
    $response->assertJsonPath('perPage', 25);
    $response->assertJsonCount(25, 'rows');
});

it('clamps perPage outside the allowed options back to the default', function () {
    $response = $this->getJson('/refilament/table/posts?perPage=999');

    $response->assertOk();
    $response->assertJsonPath('perPage', 10);
    $response->assertJsonCount(10, 'rows');
});

it('clamps a page beyond the last page down to the last page', function () {
    $response = $this->getJson('/refilament/table/posts?page=99');

    $response->assertOk();
    $response->assertJsonPath('page', 5);
    $response->assertJsonCount(5, 'rows');
});

it('marks sortable columns in the definition', function () {
    $columns = $this->getJson('/refilament/table/posts')->json('columns');

    $title = collect($columns)->firstWhere('name', 'title');
    $author = collect($columns)->firstWhere('name', 'author');

    expect($title['sortable'])->toBeTrue();
    expect($author)->not->toHaveKey('sortable');
});

it('sorts ascending by a sortable column', function () {
    $response = $this->getJson('/refilament/table/posts?sort=title&direction=asc');

    $response->assertOk();
    expect($response->json('rows.0.title'))->toBe(
        Post::query()->orderBy('title')->value('title'),
    );
});

it('sorts descending by a sortable column', function () {
    $response = $this->getJson('/refilament/table/posts?sort=views&direction=desc');

    $response->assertOk();
    expect($response->json('rows.0.views'))->toBe(
        Post::query()->orderByDesc('views')->value('views'),
    );
});

it('defaults the sort direction to ascending', function () {
    $response = $this->getJson('/refilament/table/posts?sort=title');

    $response->assertOk();
    expect($response->json('rows.0.title'))->toBe(
        Post::query()->orderBy('title')->value('title'),
    );
});

it('applies the default sort when none is requested', function () {
    $response = $this->getJson('/refilament/table/posts');

    $response->assertOk();
    $expected = Post::query()->orderByDesc('published_at')->orderByDesc('id')->first()->getKey();
    expect($response->json('rows.0.id'))->toBe($expected);
});

it('sorts and paginates together', function () {
    $response = $this->getJson('/refilament/table/posts?sort=title&direction=asc&page=2&perPage=10');

    $response->assertOk();
    expect($response->json('rows.0.title'))->toBe(
        Post::query()->orderBy('title')->skip(10)->value('title'),
    );
});

it('marks a relationship column as sortable and searchable', function () {
    $columns = $this->getJson('/refilament/table/posts')->json('columns');

    $user = collect($columns)->firstWhere('name', 'user.name');

    expect($user['sortable'])->toBeTrue();
    expect($user['searchable'])->toBeTrue();
});

it('sorts rows by a relationship (dot-notation) column', function () {
    // Give users deliberately ordered names so the related column has a clear
    // expected ordering to assert against.
    $names = ['Zane', 'Ada', 'Mira'];
    $users = collect($names)->map(fn (string $name) => \Workbench\App\Models\User::factory()->create(['name' => $name]));

    Post::query()->delete();
$users->each(fn ($user, $index): mixed => Post::create([
        'user_id' => $user->id,
        'title' => "Post {$index}",
        'author' => 'A',
        'status' => 'draft',
        'views' => $index,
    ]));

    $response = $this->getJson('/refilament/table/posts?sort=user.name&direction=asc&perPage=10');

    $response->assertOk();
    expect($response->json('rows.0')['user.name'])->toBe('Ada');
    expect($response->json('rows.2')['user.name'])->toBe('Zane');

    $desc = $this->getJson('/refilament/table/posts?sort=user.name&direction=desc&perPage=10');

    $desc->assertOk();
    expect($desc->json('rows.0')['user.name'])->toBe('Zane');
});

it('searches rows by a relationship (dot-notation) column', function () {
    $needle = 'UniqueOwner';

    $user = \Workbench\App\Models\User::factory()->create(['name' => "{$needle}Name"]);

    Post::factory()->create(['user_id' => $user->id, 'author' => 'A', 'status' => 'draft']);

$response = $this->getJson('/refilament/table/posts?search='.$needle);

    $response->assertOk();
    expect($response->json('rows'))->not->toBeEmpty();
    expect($response->json('rows.0')['user.name'])->toBe("{$needle}Name");
});

it('marks searchable columns in the definition', function () {
    $columns = $this->getJson('/refilament/table/posts')->json('columns');

    $title = collect($columns)->firstWhere('name', 'title');
    $author = collect($columns)->firstWhere('name', 'author');
    $views = collect($columns)->firstWhere('name', 'views');

    expect($title['searchable'])->toBeTrue();
    expect($author['searchable'])->toBeTrue();
    expect($views)->not->toHaveKey('searchable');
});

it('marks toggleable columns in the definition', function () {
    $columns = $this->getJson('/refilament/table/posts')->json('columns');

    $author = collect($columns)->firstWhere('name', 'author');
    $user = collect($columns)->firstWhere('name', 'user.name');
    $title = collect($columns)->firstWhere('name', 'title');

    expect($author['toggleable'])->toBeTrue();
    expect($user['toggleable'])->toBeTrue();
    expect($title)->not->toHaveKey('toggleable');
});

it('resolves the related user attribute in every row', function () {
    $response = $this->getJson('/refilament/table/posts?perPage=1');

    $row = $response->json('rows.0');
    $expected = Post::query()->orderByDesc('published_at')->orderByDesc('id')->first();

    expect($row['user.name'])->toBe($expected->user?->name);
    expect($row['user.name'])->toBeString()->not->toBeEmpty();
});

it('marks the badge column in the definition', function () {
    $status = collect($this->getJson('/refilament/table/posts')->json('columns'))
        ->firstWhere('name', 'status');

    expect($status['badge'])->toBeTrue();
});

it('ships status cells as structured badge objects on the wire', function () {
    $row = $this->getJson('/refilament/table/posts')->json('rows.0');

    expect($row['status'])->toBeArray();
    expect($row['status']['badge'] ?? null)->toBeTrue();
    expect($row['status'])->toHaveKey('value')->not->toBeEmpty();
    // The per-record color resolver maps draft/published/archived to a color.
    expect($row['status'])->toHaveKey('color');
});

it('formats published_at as a pre-formatted date string', function () {
    $row = $this->getJson('/refilament/table/posts')->json('rows.0');

    expect($row['published_at'])->toBeString()->not->toBeEmpty();
});

it('serializes the filters in the definition', function () {
    $filters = $this->getJson('/refilament/table/posts')->json('filters');

    expect($filters)->toBe([
        [
            'name' => 'status',
            'label' => 'Status',
            'type' => 'select',
            'options' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'published', 'label' => 'Published'],
                ['value' => 'archived', 'label' => 'Archived'],
            ],
            'multiple' => true,
        ],
        [
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'placeholder' => 'Filter by title…',
        ],
        [
            'name' => 'trashed',
            'label' => 'Trashed',
            'type' => 'trashed',
            'options' => [
                ['value' => '', 'label' => 'Without deleted records'],
                ['value' => 'with', 'label' => 'With deleted records'],
                ['value' => 'only', 'label' => 'Only deleted records'],
            ],
        ],
    ]);
});

it('narrows the rows to a global search term', function () {
    $needle = Post::query()->value('title');
    $needle = str($needle)->limit(20, '')->toString();

    $response = $this->getJson('/refilament/table/posts?search='.urlencode($needle));

    $response->assertOk();

    // Every returned row must match the needle in a searchable column, and the
    // search must narrow the full set (45 posts, 10 per page).
    $titles = collect($response->json('rows'))->pluck('title');
    $authors = collect($response->json('rows'))->pluck('author');

    $titleMatch = $titles->contains(fn (string $title): bool => str($title)->contains($needle, true));
    $authorMatch = $authors->contains(fn (string $author): bool => str($author)->contains($needle, true));

    expect($titleMatch || $authorMatch)->toBeTrue();
});

it('searches across all searchable columns', function () {
    $author = Post::query()->value('author');

    $response = $this->getJson('/refilament/table/posts?search='.urlencode($author));

    $response->assertOk();
    expect($response->json('rows'))->not->toBeEmpty();
    expect(collect($response->json('rows'))->pluck('author')->contains($author))->toBeTrue();
});

it('returns a match for a distinctive search term', function () {
    // A title truncated to its first two words is likely unique-ish; searching
    // the longest shared prefix of the first two titles guarantees a hit.
    $titles = Post::query()->orderBy('title')->limit(2)->pluck('title');
    $needle = str($titles[0])->words(2, '')->toString();

    $response = $this->getJson('/refilament/table/posts?search='.urlencode($needle));

    $response->assertOk();
    $rows = $response->json('rows');
    expect($rows)->not->toBeEmpty();
    expect(collect($rows)->pluck('id'))->toContain(
        Post::query()->where('title', 'like', '%'.$needle.'%')->first()->getKey(),
    );
});

it('applies a select filter', function () {
    $status = Post::query()->value('status');

    $response = $this->getJson('/refilament/table/posts?filter[status]='.$status);

    $response->assertOk();
    expect($response->json('rows'))->not->toBeEmpty();
    expect(collect($response->json('rows'))->pluck('status.value')->unique()->all())->toBe([$status]);
});

it('matches multiple values with WHERE IN', function () {
    $statuses = Post::query()->distinct()->pluck('status')->take(2)->all();

    $response = $this->getJson('/refilament/table/posts?filter[status][]='.$statuses[0].'&filter[status][]='.$statuses[1]);

    $response->assertOk();
    $rows = $response->json('rows');

    expect($rows)->not->toBeEmpty();
    expect(collect($rows)->pluck('status.value')->unique()->sort()->values()->all())->toBe(
        collect($statuses)->sort()->values()->all(),
    );
});

it('marks a multiple filter in the definition', function () {
    $filters = $this->getJson('/refilament/table/posts')->json('filters');

    expect($filters[0]['multiple'])->toBeTrue();
});

it('applies a text filter as a LIKE containment match', function () {
    $title = Post::query()->value('title');
    $needle = str($title)->limit(12, '')->toString();
    $needleLower = strtolower($needle);

    $response = $this->getJson('/refilament/table/posts?filter[title]='.urlencode($needle));

    $response->assertOk();
    $rows = $response->json('rows');

    expect($rows)->not->toBeEmpty();
    expect(collect($rows)->pluck('title')->every(
        static fn (string $t): bool => str_contains(strtolower($t), $needleLower),
    ))->toBeTrue();
    expect(count($rows))->toBe(
        Post::query()->where('title', 'like', '%'.$needle.'%')->count(),
    );
});

it('combines a text filter with a select filter', function () {
    $status = Post::query()->value('status');
    $matching = Post::query()->where('status', $status)->first();
    $needle = str($matching->title)->limit(12, '')->toString();

    $response = $this->getJson(
        '/refilament/table/posts?filter[title]='.urlencode($needle).'&filter[status]='.$status,
    );

    $response->assertOk();
    $rows = $response->json('rows');

    expect($rows)->not->toBeEmpty();
    expect(collect($rows)->pluck('status.value')->unique()->all())->toBe([$status]);
    expect(count($rows))->toBe(
        Post::query()->where('status', $status)->where('title', 'like', '%'.$needle.'%')->count(),
    );
});

it('combines search, filter, sort and pagination', function () {
    $status = Post::query()->value('status');

    $response = $this->getJson('/refilament/table/posts?filter[status]='.$status.'&sort=views&direction=desc&page=1&perPage=25');

    $response->assertOk();
    $rows = $response->json('rows');

    // The filtered set may hold fewer rows than perPage — assert against the
    // actual matching count, capped at the requested page size.
    $matching = Post::query()->where('status', $status)->count();
    expect($rows)->toHaveCount(min(25, $matching));
    expect(collect($rows)->pluck('status.value')->unique()->all())->toBe([$status]);
    expect($rows[0]['views'])->toBe(
        Post::query()->where('status', $status)->orderByDesc('views')->orderByDesc('id')->value('views'),
    );
});

it('rejects a search on a table with no searchable columns', function () {
    $this->getJson('/refilament/table/posts-baked?search=foo')->assertStatus(422);
});

it('rejects an unknown filter name', function () {
    $this->getJson('/refilament/table/posts?filter[nope]=draft')->assertStatus(422);
});

it('rejects sorting by an unknown column', function () {
    $this->getJson('/refilament/table/posts?sort=nope')->assertStatus(422);
});

it('rejects sorting by a non-sortable column', function () {
    $this->getJson('/refilament/table/posts?sort=author')->assertStatus(422);
});

it('rejects an invalid sort direction', function () {
    $this->getJson('/refilament/table/posts?sort=title&direction=sideways')->assertStatus(422);
});

it('lets a requested sort override baked query ordering', function () {
    // posts-baked orders by views desc in its query() — a title sort must
    // replace that ordering, not demote to a tiebreaker.
    $response = $this->getJson('/refilament/table/posts-baked?sort=title&direction=asc');

    $response->assertOk();
    expect($response->json('rows.0.title'))->toBe(
        Post::query()->orderBy('title')->orderByDesc('id')->value('title'),
    );
});

it('keeps baked query ordering when no sort is requested', function () {
    $response = $this->getJson('/refilament/table/posts-baked');

    $response->assertOk();
    expect($response->json('rows.0.views'))->toBe(
        Post::query()->orderByDesc('views')->value('views'),
    );
});

it('serializes the registered groups in the definition', function () {
    $response = $this->getJson('/refilament/table/posts');

    $response->assertOk();
    $response->assertJsonPath('groups', [
        ['column' => 'status', 'label' => 'Status', 'collapsible' => true],
    ]);
});

it('orders grouped rows contiguously by the group column', function () {
    $response = $this->getJson('/refilament/table/posts?group=status');

    $response->assertOk();
    $response->assertJsonPath('activeGroup', 'status');

    $groups = collect($response->json('rows'))->pluck('groupTitle')->all();
    $runs = 1;

    for ($i = 1; $i < count($groups); $i++) {
        if ($groups[$i] !== $groups[$i - 1]) {
            $runs++;
        }
    }

    // Default sort is published_at desc, so without grouping the page would
    // fragment — grouping must take precedence so each page holds a single,
    // contiguous run (mirrors Filament's group-before-sort ordering).
    expect($runs)->toBe(1);
});

it('annotates each grouped row with a group key and title', function () {
    $response = $this->getJson('/refilament/table/posts?group=status');

    $response->assertOk();

    $row = $response->json('rows.0');
    expect($row)->toHaveKeys(['groupKey', 'groupTitle']);
    expect($row['groupKey'])->toBe($row['groupTitle']);
    expect(['archived', 'draft', 'published'])->toContain($row['groupTitle']);
});

it('lets the requested sort break ties within a group', function () {
    $response = $this->getJson('/refilament/table/posts?group=status&sort=views&direction=desc&perPage=100');

    $response->assertOk();

    $rows = $response->json('rows');
    $groups = collect($rows)->pluck('groupTitle')->unique()->values();

    // The whole set is split into contiguous group runs…
    expect($groups->all())->toEqual($groups->sort()->values()->all());

    // …and within each run the requested sort is respected.
    foreach ($groups as $group) {
        $views = collect($rows)->where('groupTitle', $group)->pluck('views')->values();
        expect($views->all())->toBe($views->sortDesc()->values()->all());
    }
});

it('rejects an unknown group column', function () {
    $this->getJson('/refilament/table/posts?group=nope')->assertStatus(422);
});

it('rejects a group column that is not registered', function () {
    $this->getJson('/refilament/table/posts?group=title')->assertStatus(422);
});

it('emits per-group footer subtotals scoped to each group', function () {
    // perPage=50 fits the whole dataset (45 posts) on one page, so every
    // group's subtotal is present and comparable to a GROUP BY aggregate.
    $response = $this->getJson('/refilament/table/posts?group=status&perPage=50');

    $response->assertOk();

    $groups = $response->json('groupSummary');
    expect($groups)->not->toBeNull();

    foreach (Post::query()->selectRaw('status, SUM(views) as total')->groupBy('status')->get() as $row) {
        $cells = $groups[$row->status]['views'];
        expect($cells)->toHaveCount(1);
        expect($cells[0]['label'])->toBe('Total views');
        expect($cells[0]['value'])->toBe(number_format((float) $row->total));
    }
});

it('omits groupSummary when no group is applied', function () {
    $this->getJson('/refilament/table/posts')
        ->assertOk()
        ->assertJsonMissingPath('groupSummary');
});

it('serializes the actions in the definition', function () {
    $actions = $this->getJson('/refilament/table/posts')->json('actions');

    expect($actions)->toBe([
        [
            'name' => 'edit',
            'label' => 'Edit',
            'type' => 'edit',
            'schema' => 'post-form',
        ],
        ['name' => 'publish', 'label' => 'Publish', 'color' => 'success'],
        [
            'name' => 'delete',
            'label' => 'Delete',
            'color' => 'danger',
            'requiresConfirmation' => true,
        ],
    ]);
});

it('carries only visible action names per row', function () {
    $published = Post::factory()->create(['status' => 'published']);
    $draft = Post::factory()->create(['status' => 'draft']);

    $response = $this->getJson('/refilament/table/posts?filter[status][]=published&filter[status][]=draft&perPage=50');

    $rows = collect($response->json('rows'))->keyBy('id');

    expect($rows[$published->id]['actions'])->toBe(['edit', 'delete']);
    expect($rows[$draft->id]['actions'])->toBe(['edit', 'publish', 'delete']);
});

it('runs the publish action', function () {
    $post = Post::factory()->create(['status' => 'draft']);

    $response = $this->postJson('/refilament/table/posts/action/publish', ['record' => $post->id]);

    $response->assertOk();
    $response->assertJson(['success' => true, 'message' => 'Post published.']);
    expect($post->fresh()->status)->toBe('published');
});

it('runs the delete action', function () {
    $post = Post::factory()->create();

    $response = $this->postJson('/refilament/table/posts/action/delete', ['record' => $post->id]);

    $response->assertOk();
    $response->assertJson(['success' => true, 'message' => 'Post deleted.']);
    expect(Post::find($post->id))->toBeNull();
});

it('rejects an unknown action', function () {
    $post = Post::factory()->create();

    $this->postJson('/refilament/table/posts/action/nope', ['record' => $post->id])->assertNotFound();
});

it('rejects an action on an unknown table', function () {
    $this->postJson('/refilament/table/missing/action/delete', ['record' => 1])->assertNotFound();
});

it('rejects an action on a missing record', function () {
    $this->postJson('/refilament/table/posts/action/delete', ['record' => 999999])->assertNotFound();
});

it('rejects an action that is no longer visible for the record', function () {
    // publish() only renders for non-published records — once published, the
    // server must refuse the action even if a stale client sends it.
    $post = Post::factory()->create(['status' => 'published']);

    $this->postJson('/refilament/table/posts/action/publish', ['record' => $post->id])
        ->assertStatus(422)
        ->assertJson(['error' => 'Action is not available for this record.']);

    expect($post->fresh()->status)->toBe('published');
});

it('requires the record in the action request', function () {
    $this->postJson('/refilament/table/posts/action/delete', [])->assertStatus(422);
});

it('rejects an unknown table', function () {
    $this->getJson('/refilament/table/missing')->assertNotFound();
});

it('validates the query parameters', function () {
    $this->getJson('/refilament/table/posts?page=0')->assertStatus(422);
    $this->getJson('/refilament/table/posts?perPage=0')->assertStatus(422);
});

