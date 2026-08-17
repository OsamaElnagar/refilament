<?php

declare(strict_types=1);

use Refilament\Refilament\Navigation\NavigationGroup;
use Refilament\Refilament\Navigation\NavigationItem;
use Refilament\Refilament\Panel\Panel;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Support\Enums\PanelsRenderHook;
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
        'path' => 'refilament',
        'brandName' => 'Refilament',
        'sidebarCollapsible' => false,
        'topNavigation' => false,
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

it('serializes a brand logo and top-navigation flag in the shell contract', function () {
    $panel = Panel::make()
        ->id('refilament')
        ->brandName('Acme')
        ->brandLogo('https://example.com/logo.svg')
        ->topNavigation()
        ->toArray();

    expect($panel['brandLogo'])->toBe('https://example.com/logo.svg');
    expect($panel['topNavigation'])->toBeTrue();
});

it('resolves a closure brand logo at serialization', function () {
    $panel = Panel::make()->brandLogo(fn (): string => 'https://example.com/logo.svg')->toArray();

    expect($panel['brandLogo'])->toBe('https://example.com/logo.svg');
});

it('omits brandLogo when unset', function () {
    expect(Panel::make()->toArray())->not->toHaveKey('brandLogo');
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
    expect($contract['groups'][1]['items'][0]['badge'])->toBe('3');
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

it('serializes armed render hooks into the shell contract', function () {
    $panel = Panel::make()
        ->renderHook(PanelsRenderHook::SidebarFooter, fn (): string => '<footer/>')
        ->renderHook(PanelsRenderHook::TopbarEnd, fn (): string => view('vendor.render-hooks.topbar')->render());

    expect($panel->toArray()['renderHooks'])->toBe([
        'panels::sidebar.footer' => '<footer/>',
        'panels::topbar.end' => view('vendor.render-hooks.topbar')->render(),
    ]);
});

it('resolves every PanelsRenderHook case to its Filament slot value', function () {
    $slots = collect(PanelsRenderHook::cases())->map(fn (PanelsRenderHook $hook) => $hook->value);

    expect($slots)->each->toStartWith('panels::');
});

it('accepts a raw custom slot string and a plain HTML string', function () {
    $panel = Panel::make()->renderHook('panels::sidebar.footer', '<footer/>');
    $custom = Panel::make()->renderHook('my-custom-slot', '<div>custom</div>');

    expect($panel->toArray()['renderHooks'])->toBe(['panels::sidebar.footer' => '<footer/>'])
        ->and($custom->toArray()['renderHooks'])->toBe(['my-custom-slot' => '<div>custom</div>']);
});

it('omits renderHooks when no hooks are armed', function () {
    expect(Panel::make()->toArray())->not->toHaveKey('renderHooks');
});

it('serializes the database-notifications bell contract', function () {
    $panel = Panel::make()->databaseNotifications()->databaseNotificationsPolling('10s');

    expect($panel->toArray()['notifications'])->toBe(['polling' => '10s']);
});

it('defaults the bell polling interval to 30s', function () {
    expect(Panel::make()->databaseNotifications()->toArray()['notifications'])->toBe(['polling' => '30s']);
});

it('omits the notifications key when the bell is disabled', function () {
    expect(Panel::make()->toArray())->not->toHaveKey('notifications');
});

it('serializes badge color and tooltip on a nav item', function () {
    $panel = Panel::make()
        ->navigationItems([
            NavigationItem::make('Inbox')
                ->url('/inbox')
                ->badge(4)
                ->badgeColor('danger')
                ->badgeTooltip('4 unread'),
        ]);

    $item = $panel->toArray()['items'][0];

    expect($item['badge'])->toBe('4');
    expect($item['badgeColor'])->toBe('danger');
    expect($item['badgeTooltip'])->toBe('4 unread');
});

it('omits badge color and tooltip when not configured', function () {
    $panel = Panel::make()->navigationItems([
        NavigationItem::make('Posts')->url('/posts')->badge(2),
    ]);

    $item = $panel->toArray()['items'][0];

    expect($item)->not->toHaveKey('badgeColor');
    expect($item)->not->toHaveKey('badgeTooltip');
});
