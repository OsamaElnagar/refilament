<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Pages;

use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Components\Select;
use Refilament\Refilament\Schemas\Components\Toggle;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Refilament\Clusters\AccountCluster;

/**
 * A standalone panel page inside the Account cluster (the page-clusters
 * slice) — declaring `$cluster` serves it at /refilament/account/preferences
 * (the cluster's slug prefixes its own), groups it under the cluster's
 * sidebar entry, and adds the Account crumb to its breadcrumbs. A plain
 * page-form host otherwise: the generic refilament/page-form component
 * renders the form, submitted through the typed submit endpoint.
 */
class AccountPreferencesPage extends Page
{
    /** @var class-string<AccountCluster> */
    protected static ?string $cluster = AccountCluster::class;

    protected static ?string $navigationLabel = 'Preferences';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static bool $shouldRegisterNavigation = true;

    protected static bool $hasUnsavedDataChangesAlert = true;

    public static function getSlug(): string
    {
        return 'preferences';
    }

    public static function getInertiaComponent(): string
    {
        return 'refilament/page-form';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('timezone')
                ->label('Timezone')
                ->options([
                    'UTC' => 'UTC',
                    'America/New_York' => 'America/New_York',
                    'Europe/London' => 'Europe/London',
                    'Africa/Cairo' => 'Africa/Cairo',
                ])
                ->default('UTC')
                ->searchable(),
            Toggle::make('marketing_emails')
                ->label('Marketing emails')
                ->default(true),
        ])->submitUsing(static function (array $data): void {
            // Demo only — preferences are client-held; nothing persisted.
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPanelViewData(Refilament $refilament): array
    {
        return [
            'description' => 'A page inside the Account cluster — served at /refilament/account/preferences with the cluster crumb above its own.',
        ];
    }
}
