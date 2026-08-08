<?php

declare(strict_types=1);

use Refilament\Refilament\Navigation\NavigationGroup;
use Refilament\Refilament\Navigation\NavigationItem;
use Refilament\Refilament\Panel\Panel;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Tests\Fixtures\DemoResource;
use Refilament\Refilament\Tests\Fixtures\PagesResource;
use Refilament\Refilament\Widgets\StatsOverviewWidget;
use Workbench\App\Models\Post;

it('serializes the panel shell contract for the sidebar', function () {
    $panel = Panel::make()
        ->id('refilament')
        ->brandName('Refilament')
        ->resources([DemoResource::class]);

    expect($panel->toArray())->toBe([
        'id' => 'refilament',
        'brandName' => 'Refilament',
        'sidebarCollapsible' => false,
        'dashboardUrl' => '/refilament',
        'groups' => [],
        'items' => [
            [
                'key' => DemoResource::class,
                'label' => 'Posts',
                'url' => '/refilament/demo',
                'children' => [],
            ],
        ],
    ]);
});

it('buckets navigation items into groups by their group name', function () {
    $panel = Panel::make()
        ->resources([DemoResource::class])
        ->navigationGroups([
            NavigationGroup::make('Content'),
            NavigationGroup::make('System'),
        ])
        ->navigationItems([
            NavigationItem::make('Posts')
                ->url('/refilament/posts')
                ->group('Content'),
            NavigationItem::make('Users')
                ->url('/refilament/users')
                ->group('Content'),
            NavigationItem::make('Settings')
                ->url('/refilament/settings')
                ->group('System')
                ->badge(3)
                ->icon('heroicon-o-cog'),
            NavigationItem::make('Home')->url('/'),
        ]);

    $contract = $panel->toArray();

    expect($contract['groups'])->toHaveCount(2);
    expect($contract['groups'][0]['label'])->toBe('Content');
    expect($contract['groups'][0]['items'])->toBe([
        ['key' => 'Posts', 'label' => 'Posts', 'url' => '/refilament/posts', 'children' => []],
        ['key' => 'Users', 'label' => 'Users', 'url' => '/refilament/users', 'children' => []],
    ]);
    expect($contract['groups'][1]['label'])->toBe('System');
    expect($contract['groups'][1]['items'][0]['badge'])->toBe(3);
    expect($contract['groups'][1]['items'][0]['icon'])->toBe('heroicon-o-cog');

    expect($contract['items'])->toBe([
        [
            'key' => DemoResource::class,
            'label' => 'Posts',
            'url' => '/refilament/demo',
            'children' => [],
        ],
        ['key' => 'Home', 'label' => 'Home', 'url' => '/', 'children' => []],
    ]);
});

it('sorts group members by their sort value', function () {
    $panel = Panel::make()
        ->navigationGroups([NavigationGroup::make('Content')])
        ->navigationItems([
            NavigationItem::make('Z')->url('/z')->group('Content')->sort(2),
            NavigationItem::make('A')->url('/a')->group('Content')->sort(1),
        ]);

    $contract = $panel->toArray();

    expect($contract['groups'][0]['items'])->toBe([
        ['key' => 'A', 'label' => 'A', 'url' => '/a', 'children' => []],
        ['key' => 'Z', 'label' => 'Z', 'url' => '/z', 'children' => []],
    ]);
});

it('renders a registered group with no members as an empty heading', function () {
    $panel = Panel::make()
        ->navigationGroups([NavigationGroup::make('System')]);

    expect($panel->toArray()['groups'])->toBe([
        ['label' => 'System', 'items' => []],
    ]);
});

it('omits resources that should not register navigation', function () {
    $resource = new class extends Resource
    {
        /** @var class-string */
        protected static ?string $model = Post::class;

        protected static bool $shouldRegisterNavigation = false;

        public static function table(Table $table): Table
        {
            return $table;
        }

        public static function form(Schema $schema): Schema
        {
            return $schema;
        }
    };

    $panel = Panel::make()->resources([$resource::class]);

    expect($panel->toArray()['items'])->toBe([]);
});

it('exposes the collapse-on-desktop toggle', function () {
    $panel = Panel::make()->sidebarCollapsibleOnDesktop();

    expect($panel->toArray()['sidebarCollapsible'])->toBeTrue();
});

it('serializes the dashboard url and omits colors when none are set', function () {
    $panel = Panel::make()->dashboardUrl('/admin');

    expect($panel->toArray()['dashboardUrl'])->toBe('/admin');
    expect($panel->toArray())->not->toHaveKey('colors');
});

it('serializes colors when set', function () {
    $panel = Panel::make()->colors(['primary' => '#123456', 'primary_foreground' => '#ffffff']);

    expect($panel->toArray()['colors'])->toBe([
        'primary' => '#123456',
        'primary_foreground' => '#ffffff',
    ]);
});

it('exposes the registered widget classes', function () {
    $widget = new class extends StatsOverviewWidget
    {
        public static function make(): static
        {
            return parent::make()->heading('Demo');
        }
    };

    $panel = Panel::make()->widgets([$widget::class]);

    expect($panel->getWidgets())->toBe([$widget::class]);
});

it('registers a nav item for an opt-in custom resource page', function () {
    $panel = Panel::make()->resources([PagesResource::class]);

    $items = $panel->toArray()['items'];
    $labels = collect($items)->pluck('label')->all();

    // The resource itself + its one opt-in custom page. The 'other' custom
    // page does not opt in, so it is absent.
    expect($labels)->toBe(['Posts', 'Stats']);

    $stats = collect($items)->firstWhere('label', 'Stats');
    expect($stats['url'])->toBe('/refilament/pages/stats');
    expect($stats['icon'])->toBe('chart-bar');
});

it('skips custom pages that have not opted into navigation', function () {
    $panel = Panel::make()->resources([PagesResource::class]);

    $labels = collect($panel->toArray()['items'])->pluck('label')->all();

    // 'Stats' opts in, 'other' does not — the non-opted page never appears.
    expect($labels)->not->toContain('Other');
    expect($labels)->toContain('Stats');
});
