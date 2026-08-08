<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Resources\RelationManagers;

use Refilament\Refilament\Resources\RelationManagers\RelationManager;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Refilament\Schemas\CommentsForm;
use Workbench\App\Refilament\Tables\CommentsTable;

/**
 * The comments relation under a post (slice 1.8). It hosts the post's
 * `comments()` records, reusing the standalone CommentsTable / CommentsForm
 * classes — the resource's own table/form are not redeclared here.
 *
 * The scoped endpoint GET /refilament/relation/posts/{record}/comments
 * rebuilds Post::comments() for the given owner on every request and serves
 * this table's payload filtered to that owner.
 */
class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public static function table(Table $table): Table
    {
        return CommentsTable::configure($table);
    }

    public static function form(Schema $schema): Schema
    {
        return CommentsForm::configure($schema);
    }
}
