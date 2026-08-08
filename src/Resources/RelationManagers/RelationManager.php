<?php

declare(strict_types=1);

namespace Refilament\Refilament\Resources\RelationManagers;

use Illuminate\Support\Str;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;

/**
 * A relation manager (slice 1.8 — docs/CONTRACT.md, "Relations") hosts one
 * parent model's to-many records under the owner's page, mirroring Filament's
 * `panels/src/Resources/RelationManagers/RelationManager.php`.
 *
 * The subclass names the Eloquent relationship on the owner model
 * (`$relationship`) and provides `table()` / `form()` definitions — standalone
 * classes a resource may also reuse (the composable pattern, docs/ARCHITECTURE.md,
 * "Relation managers & reusable table/form classes").
 *
 * The typed relation endpoint (GET /refilament/relation/{resource}/{record}/{relation})
 * resolves a manager + owner, rebuilds the scoped to-many query from the parent
 * on every request, and serves the manager's table definition one page at a
 * time — identical grain to the table index endpoint, so no component state
 * survives between requests.
 */
abstract class RelationManager
{
    /**
     * The name of the to-many relationship on the owner model that this
     * manager lists, e.g. `'comments'` for a `Post::comments()` hasMany.
     * Left undefined here so a manager that forgets to set it fails loudly.
     */
    protected static string $relationship;

    /**
     * An optional display title for this manager, e.g. `'User comments'`.
     * Falls back to the headline of the relationship name when unset.
     */
    protected static ?string $title = null;

    /**
     * The table definition served by the scoped relation endpoint. Column,
     * filter, search, sort and action configuration go here; the controller
     * supplies the owner-scoped relationship as the query afterwards, so a
     * `table()` must not bake in its own model-level query.
     */
    abstract public static function table(Table $table): Table;

    /**
     * The form schema for modal create/edit of related records (a later
     * slice). Unused by the list endpoint; kept as the Filament-mirroring
     * surface so the same class is reused verbatim by the create/edit modals.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * The relationship name this manager hosts.
     */
    public static function getRelationshipName(): string
    {
        return static::$relationship;
    }

    /**
     * The display title used on the owner's page, mirroring Filament's
     * RelationManager::getTitle(). Falls back to the headline of the
     * relationship name (e.g. 'comments' -> 'Comments').
     */
    public static function getTitle(): string
    {
        return static::$title ?? Str::headline(static::$relationship);
    }
}
