<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Refilament\Refilament\Refilament;
use Workbench\App\Models\User;
use Workbench\App\Refilament\Clusters\AccountCluster;
use Workbench\App\Refilament\Pages\AccountPreferencesPage;
use Workbench\App\Refilament\Pages\SettingsPage;
use Workbench\App\Refilament\Resources\UserResource;

/**
 * Fixture policy for the cluster redirect test — denies the users resource
 * so the cluster's redirect skips it and lands on the preferences page.
 */
class ClusterDenyUsersPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }
}

/**
 * The page-clusters slice — mirroring Filament's `Filament\Clusters\Cluster`:
 * a page-like class grouping pages and resources under one sidebar entry
 * (sub-navigation), a cluster-prefixed URL for its pages, a cluster crumb in
 * its members' breadcrumbs, and a redirect route at its own slug.
 */
it('registers the cluster and resolves its clustered components', function () {
    $refilament = app(Refilament::class);

    expect($refilament->getClusters())->toContain(AccountCluster::class)
        ->and($refilament->getClusterClass('account'))->toBe(AccountCluster::class);

    $components = $refilament->getClusteredComponents(AccountCluster::class);

    expect($components)->toContain(AccountPreferencesPage::class)
        ->and($components)->toContain(UserResource::class);
});

it('prepends the cluster slug to a clustered page URL path', function () {
    // The bare slug stays the consumer's own segment; the full path carries
    // the cluster prefix (mirroring Filament, where prependClusterSlug()
    // applies at route/nav build time).
    expect(AccountPreferencesPage::getSlug())->toBe('preferences')
        ->and(AccountPreferencesPage::getSlugPath())->toBe('account/preferences')
        ->and(AccountPreferencesPage::getCluster())->toBe(AccountCluster::class)
        ->and(AccountPreferencesPage::getClusterUrl())->toBe(route('refilament.cluster', ['cluster' => 'account']));
});

it('serves a clustered page at its cluster-prefixed slug with the cluster crumb', function () {
    $this->get('/refilament/account/preferences', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/page-form')
        ->assertJsonPath('props.formTitle', 'Preferences')
        // The cluster crumb leads the chain, linked to the cluster's URL.
        ->assertJsonPath('props.breadcrumbs.0.label', 'Account')
        ->assertJsonPath('props.breadcrumbs.0.url', route('refilament.cluster', ['cluster' => 'account']))
        ->assertJsonPath('props.breadcrumbs.1.label', 'Preferences');
});

it('redirects the cluster URL to its first accessible member', function () {
    // The users resource is a member, so the cluster lands on its list page.
    $this->get('/refilament/account')
        ->assertRedirect('/refilament/users');
});

it('skips inaccessible members when redirecting the cluster URL', function () {
    Gate::policy(User::class, ClusterDenyUsersPolicy::class);

    // The users resource is denied — the redirect falls through to the page.
    $this->actingAs(User::factory()->create())
        ->get('/refilament/account')
        ->assertRedirect('/refilament/account/preferences');
});

it('serializes the cluster as a sidebar parent with its members as children', function () {
    $response = $this->get('/refilament', ['X-Inertia' => 'true'])
        ->assertOk();

    $items = $response->json('props.refilament.panel.items');

    $clusterItem = collect($items)->firstWhere('key', AccountCluster::class);

    expect($clusterItem)->not->toBeNull()
        ->and($clusterItem['label'])->toBe('Account')
        ->and($clusterItem['url'])->toBe('/refilament/account')
        ->and($clusterItem['children'])->not->toBeNull();

    $childKeys = array_column($clusterItem['children'], 'key');

    expect($childKeys)->toContain(AccountPreferencesPage::class)
        ->and($childKeys)->toContain(UserResource::class);

    // The clustered members do not also appear at the top level.
    $topKeys = array_column($items, 'key');

    expect($topKeys)->not->toContain(AccountPreferencesPage::class)
        ->and($topKeys)->not->toContain(UserResource::class);
});

it('adds the cluster crumb to a clustered resource breadcrumbs', function () {
    $this->get('/refilament/users', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.breadcrumbs.0.label', 'Account')
        ->assertJsonPath('props.breadcrumbs.1.label', 'Users');
});

it('keeps unclustered page slugs unchanged', function () {
    // The settings page declares no cluster — its slug and breadcrumbs are
    // untouched by the cluster machinery.
    expect(SettingsPage::getSlug())->toBe('settings');

    $this->get('/refilament/settings', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonMissingPath('props.breadcrumbs.0.label');
});
