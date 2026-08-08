<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Refilament\Resources\PostResource;
use Workbench\App\Refilament\Schemas\PostsForm;
use Workbench\App\Refilament\Tables\PostsTable;

it('exposes the posts table through a standalone configure() class', function () {
    $table = PostsTable::configure(Table::make());

    expect($table->getId())->toBe('posts')
        ->and($table->getHeading())->toBe('Posts')
        ->and($table->getColumns())->not->toBeEmpty()
        ->and($table->getFilters())->not->toBeEmpty()
        ->and($table->getActions())->toHaveCount(3);
});

it('lets the resource delegate its table to the standalone class', function () {
    // The delegated path must serialize identically to the class called
    // directly — same definition, same id, same filters and actions.
    expect(PostResource::table(Table::make())->toArray())
        ->toBe(PostsTable::configure(Table::make())->toArray());

    // The table's serialized id drives the client's fetch URLs, so it must
    // never drift from the resource's endpoint id (the reviewer-flagged
    // footgun: the standalone class hardcodes 'posts').
    expect(PostResource::table(Table::make())->getId())->toBe(PostResource::getTableId());
});

it('exposes the posts form through a standalone configure() class', function () {
    $schema = PostsForm::configure(Schema::make());

    expect($schema->getId())->toBe('post-form')
        ->and($schema->getComponents())->not->toBeEmpty()
        ->and($schema->getSuccessMessage())->toBe('Post created.');
});

it('lets the resource delegate its form to the standalone class', function () {
    expect(PostResource::form(Schema::make())->toArray())
        ->toBe(PostsForm::configure(Schema::make())->toArray());

    // Same drift guard for the form: the schema id addresses the submit and
    // resolve-options endpoints.
    expect(PostResource::form(Schema::make())->getId())->toBe(PostResource::getFormId());
});
