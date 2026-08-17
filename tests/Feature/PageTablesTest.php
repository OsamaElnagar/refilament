<?php

declare(strict_types=1);

use Refilament\Refilament\Refilament;
use Refilament\Refilament\Tests\Fixtures\TablePageResource;
use Refilament\Refilament\Tests\Fixtures\TableResourcePage;
use Workbench\App\Models\Post;
use Workbench\App\Refilament\Pages\PostsTablePage;

it('serializes the page table payload on a standalone page', function () {
    Post::factory()->count(3)->create();

    $response = $this->get('/refilament/posts-table', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.id', PostsTablePage::getTableId())
        ->assertJsonPath('props.tableTitle', 'Posts table')
        ->assertJsonPath('props.description', 'A page hosting a table — the report/dashboard idiom. Pagination, sorting, search and the status filter all run through the typed table endpoints.')
        // The table definition (5 columns) plus the first page of rows.
        ->assertJsonCount(5, 'props.columns')
        ->assertJsonCount(3, 'props.rows')
        ->assertJsonPath('props.total', 3);

    // Every created post shows up across the page's rows (the factory's
    // rows sort by published_at desc, so the first row is not stable).
    $titles = Post::pluck('title')->all();
    $rowTitles = array_column($response->json('props.rows'), 'title');

    expect($rowTitles)->toHaveCount(3);

    foreach ($titles as $title) {
        expect(in_array($title, $rowTitles, true))->toBeTrue();
    }
});

it('serves the page table through the typed table endpoint', function () {
    Post::factory()->count(12)->create();

    $response = $this->getJson('/refilament/table/'.PostsTablePage::getTableId())
        ->assertOk()
        ->assertJsonPath('id', PostsTablePage::getTableId());

    // Pagination is server-side — the default 10-per-page clamp to 10 rows.
    expect($response->json('total'))->toBe(12)
        ->and($response->json('lastPage'))->toBe(2)
        ->and(count($response->json('rows')))->toBe(10);

    // Page 2 carries the remainder.
    $this->getJson('/refilament/table/'.PostsTablePage::getTableId().'?page=2')
        ->assertOk()
        ->assertJsonCount(2, 'rows');
});

it('merges a custom resource page table into the payload', function () {
    $refilament = app(Refilament::class);
    $refilament->registerResources(TablePageResource::class);
    $refilament->registerPageRoutes();

    Post::factory()->count(2)->create();

    $this->get('/refilament/table-page/report', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/page-table')
        ->assertJsonPath('props.id', TableResourcePage::getTableId())
        ->assertJsonPath('props.tableTitle', 'Table Resource Page')
        ->assertJsonPath('props.description', 'A custom resource page hosting a table.')
        ->assertJsonCount(2, 'props.columns')
        ->assertJsonCount(2, 'props.rows');

    // The resource page's table resolves through the typed endpoint too.
    $this->getJson('/refilament/table/'.TableResourcePage::getTableId())
        ->assertOk()
        ->assertJsonPath('total', 2);
});

it('omits the table payload from pages that declare no table', function () {
    $this->get('/refilament/settings', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonMissingPath('props.columns')
        ->assertJsonMissingPath('props.rows')
        ->assertJsonMissingPath('props.tableTitle');
});
