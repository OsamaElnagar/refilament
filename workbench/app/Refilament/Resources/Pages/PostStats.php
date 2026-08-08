<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Resources\Pages;

use Refilament\Refilament\Resources\Pages\Page;
use Workbench\App\Models\Post;
use Workbench\App\Refilament\Resources\PostResource;

/**
 * A custom resource page (slice 1.6) — registered in PostResource::getPages()
 * under 'stats' and served at /refilament/posts/stats. Computes its own extra
 * props server-side via getViewData() — the pages-as-tables idiom applied to
 * a computed report instead of a table. The React component
 * (refilament/post-stats) renders the numbers and links back to the list.
 */
final class PostStats extends Page
{
    /** @var class-string<PostResource> */
    protected static ?string $resource = PostResource::class;

    protected static string $routePath = '/stats';

    protected static ?string $navigationLabel = 'Stats';

    protected static ?string $navigationIcon = 'chart-bar';

    // Surface this custom page in the panel sidebar (slice 1.9) — custom
    // resource pages opt in via shouldRegisterNavigation() on Page.
    protected static bool $shouldRegisterNavigation = true;

    public static function getInertiaComponent(): string
    {
        return 'refilament/post-stats';
    }

    /**
     * @return array<string, mixed>
     */
    public static function getViewData(string $resource): array
    {
        return [
            'stats' => [
                'total' => Post::count(),
                'published' => Post::where('status', 'published')->count(),
                'draft' => Post::where('status', 'draft')->count(),
                'archived' => Post::where('status', 'archived')->count(),
            ],
        ];
    }
}
