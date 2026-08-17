<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;
use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Actions\BulkAction;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Tests\Fixtures\GatedModel;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class GatedModelPolicy
{
    // Laravel 13 only invokes a policy method for a guest (no authenticated
    // user — the panel is open by default) when the first parameter is
    // explicitly nullable (the canonical guest-opt-in signature). The tests
    // run unauthenticated, so the fixture policy declares that opt-in; the
    // authorization logic itself never depends on the user. (An untyped
    // `$user = null` before a required parameter would also be deprecated by
    // PHP 8.5.)
    public function delete(?User $user, GatedModel $model): bool
    {
        return ! $model->locked;
    }

    public function publish(?User $user, GatedModel $model): bool
    {
        return ! $model->locked;
    }
}

beforeEach(function () {
    // Registered per-test (after the app boots) — the fixture policy only
    // ever applies to the dedicated GatedModel class, so it cannot leak into
    // any other test.
    Gate::policy(GatedModel::class, GatedModelPolicy::class);
});

it('allows an action with no authorization declared', function () {
    $action = Action::make('delete');

    expect($action->isAuthorized())->toBeTrue();
    expect($action->isAuthorizedFor(new GatedModel))->toBeTrue();
});

it('allows a declared ability when the policy permits', function () {
    $action = Action::make('delete')->authorize('delete');

    expect($action->isAuthorizedFor(new GatedModel))->toBeTrue();
});

it('denies a declared ability when the policy forbids', function () {
    $locked = new GatedModel;
    $locked->locked = true;

    $action = Action::make('delete')->authorize('delete');

    expect($action->isAuthorizedFor($locked))->toBeFalse();
});

it('allows an ability no policy declares (permissive default)', function () {
    // No policy declares `edit` on GatedModel — the permissive default allows
    // it, exactly like Resource::getAuthorizationResponse on a fresh install.
    $action = Action::make('edit')->authorize('edit');

    expect($action->isAuthorizedFor(new GatedModel))->toBeTrue();
});

it('allows a record-less action with no policy at all', function () {
    // The workbench Post model has no registered policy — a meta-ability on a
    // header action stays allowed (fresh-install reality).
    $action = Action::make('create')->authorize('create', Post::class);

    expect($action->isAuthorized())->toBeTrue();
});

it('requires every declared ability with authorize()', function () {
    $action = Action::make('publish')->authorize(['delete', 'publish']);

    expect($action->isAuthorizedFor(new GatedModel))->toBeTrue();

    $locked = new GatedModel;
    $locked->locked = true;

    expect($action->isAuthorizedFor($locked))->toBeFalse();
});

it('passes authorizeAny when at least one declared ability is allowed', function () {
    $action = Action::make('publish')->authorizeAny(['delete', 'publish']);

    expect($action->isAuthorizedFor(new GatedModel))->toBeTrue();
});

it('denies authorizeAny when every declared ability is denied', function () {
    $locked = new GatedModel;
    $locked->locked = true;

    $action = Action::make('publish')->authorizeAny(['delete', 'publish']);

    expect($action->isAuthorizedFor($locked))->toBeFalse();
});

it('evaluates a closure authorization with the record', function () {
    $action = Action::make('delete')->authorize(
        static fn (GatedModel $record): bool => ! $record->locked,
    );

    expect($action->isAuthorizedFor(new GatedModel))->toBeTrue();

    $locked = new GatedModel;
    $locked->locked = true;

    expect($action->isAuthorizedFor($locked))->toBeFalse();
});

it('evaluates a closure authorization without a record', function () {
    expect(Action::make('x')->authorize(static fn (): bool => false)->isAuthorized())->toBeFalse();
    expect(Action::make('x')->authorize(static fn (): bool => true)->isAuthorized())->toBeTrue();
});

it('folds authorization into per-record visibility', function () {
    $action = Action::make('delete')->authorize('delete');

    expect($action->isVisibleFor(new GatedModel))->toBeTrue();

    $locked = new GatedModel;
    $locked->locked = true;

    expect($action->isVisibleFor($locked))->toBeFalse();
});

it('gives bulk actions the same general authorization gate', function () {
    expect(BulkAction::make('wipe')->isAuthorized())->toBeTrue();
    expect(BulkAction::make('wipe')->authorize(static fn (): bool => false)->isAuthorized())->toBeFalse();
    expect(BulkAction::make('wipe')->authorize(static fn (): bool => true)->isAuthorized())->toBeTrue();
});

it('filters records through per-record authorization', function () {
    $bulk = BulkAction::make('wipe')->authorizeIndividualRecords('delete');

    $locked = new GatedModel;
    $locked->locked = true;

    $records = new EloquentCollection([new GatedModel, $locked, new GatedModel]);

    expect($bulk->filterRecords($records))->toHaveCount(2);
});

it('keeps every record when the per-record ability is undeclared (permissive)', function () {
    // No policy declares `restore` on GatedModel — permissive default keeps
    // all selected records, mirroring the fresh-install behavior.
    $bulk = BulkAction::make('restore')->authorizeIndividualRecords('restore');

    $records = new EloquentCollection([new GatedModel, new GatedModel]);

    expect($bulk->filterRecords($records))->toHaveCount(2);
});

it('omits unauthorized header and toolbar actions from the table payload', function () {
    $table = Table::make('protected')
        ->columns([Column::make('id')])
        ->headerActions([
            Action::make('create')->authorize(static fn (): bool => false),
        ])
        ->toolbarActions([
            BulkAction::make('wipe')->authorize(static fn (): bool => false),
        ]);

    $payload = $table->toArray();

    expect($payload)->not->toHaveKey('headerActions');
    expect($payload)->not->toHaveKey('toolbarActions');
});

it('serializes authorized header and toolbar actions', function () {
    $table = Table::make('protected')
        ->columns([Column::make('id')])
        ->headerActions([
            Action::make('create')->authorize(static fn (): bool => true),
        ])
        ->toolbarActions([
            BulkAction::make('wipe')->authorize(static fn (): bool => true),
        ]);

    $payload = $table->toArray();

    expect($payload['headerActions'])->toHaveCount(1);
    expect($payload['toolbarActions'])->toHaveCount(1);
});
