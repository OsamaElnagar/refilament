<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Refilament\Refilament\Resources\Pages\PageRegistration;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

/**
 * A resource that extends the built-in pages with two custom 'stats' pages —
 * one opted into navigation, one not — to exercise the panel's custom-page
 * nav collection (slice 1.9).
 */
final class PagesResource extends Resource
{
    /** @var class-string */
    protected static ?string $model = Post::class;

    protected static ?string $tableId = 'pages';

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            ...parent::getPages(),
            'stats' => new PageRegistration(
                page: CustomStatsPage::class,
                path: '/stats',
                route: static fn (string $name): Route => RouteFacade::get('/unused')->name($name),
            ),
            'other' => new PageRegistration(
                page: NonCustomStatsPage::class,
                path: '/stats',
                route: static fn (string $name): Route => RouteFacade::get('/unused')->name($name),
            ),
        ];
    }
}
