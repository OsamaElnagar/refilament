<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Resources\Pages;

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Actions\CreateAction;
use Refilament\Refilament\Resources\Pages\ListRecords;
use Refilament\Refilament\Widgets\Widget;
use Workbench\App\Refilament\Resources\PostResource;
use Workbench\App\Refilament\Widgets\ContentOverview;

/**
 * The demo posts list page (slice 1.10 — docs/ROADMAP.md "1.10 Page header
 * actions"). The class a real consumer's generated Pages/ListPosts would look
 * like: it declares its resource, overrides getHeaderActions() (the default
 * CreateAction — resolved to the create page URL at request time) and adds a
 * header widget strip above the table.
 */
class ListPosts extends ListRecords
{
    /**
     * The resource this page belongs to.
     *
     * @var class-string<PostResource>|null
     */
    protected static ?string $resource = PostResource::class;

    /**
     * Header actions (slice 1.10) — the default CreateAction navigates to the
     * create page (the resource registers one), rendered top-right of the
     * list page header. Override to add more actions beside it.
     *
     * @return array<int, Action>
     */
    protected static function getHeaderActions(string $resource): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Header widgets (slice 1.10) — the same ContentOverview stats strip the
     * dashboard shows, rendered above the posts table. Widgets are static
     * snapshots, serialized with the page payload.
     *
     * @return array<int, Widget>
     */
    protected static function getHeaderWidgets(): array
    {
        return [
            ContentOverview::make(),
        ];
    }

    /**
     * The header widget grid — the stats strip spans the full width under the
     * default 2-column grid (it sets its own 4-column internal layout).
     */
    public static function getHeaderWidgetsColumns(): int
    {
        return 2;
    }
}
