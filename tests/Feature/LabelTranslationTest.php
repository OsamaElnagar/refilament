<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Refilament\Refilament\Navigation\NavigationGroup;
use Refilament\Refilament\Navigation\NavigationItem;
use Refilament\Refilament\Schemas\Components\Tab;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Tables\Action;
use Refilament\Refilament\Tables\BulkAction;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Group;
use Refilament\Refilament\Tables\SelectFilter;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Tables\TextFilter;
use Refilament\Refilament\Tables\TrashedFilter;
use Refilament\Refilament\Widgets\StatsOverviewWidget\Stat;

/**
 * Slice 4.3 - i18n, server-side label translation (docs/ROADMAP.md "4.3 i18n").
 *
 * Labels live on the server and are serialized to the client, so localization
 * is a purely server-side concern. Mirroring Filament's HasLabel, each label
 * seat carries an opt-in translate flag: when enabled, the label string is
 * treated as a translation key and resolved through Laravel's translator at
 * serialization time. Off by default, so existing labels pass through
 * verbatim and a fresh install needs no lang files.
 */
it('returns the raw label when translation is not enabled', function () {
    $component = TextInput::make('title')->label('refilament::messages.posts.title');

    expect($component->getLabel())->toBe('refilament::messages.posts.title');
});

it('translates the label when the translate flag is enabled', function () {
    $component = TextInput::make('title')
        ->label('refilament::messages.posts.title')
        ->translateLabel();

    expect($component->getLabel())->toBe('Post title');
});

it('falls back to the raw key for an untranslated label', function () {
    $component = TextInput::make('title')
        ->label('missing::key.here')
        ->translateLabel();

    expect($component->getLabel())->toBe('missing::key.here');
});

it('translates a column label when enabled', function () {
    $column = Column::make('title')->label('refilament::messages.posts.title')->translateLabel();

    expect($column->getLabel())->toBe('Post title');
});

it('serializes the translated label and heading on the table payload', function () {
    $table = Table::make('posts')
        ->id('posts')
        ->heading('refilament::messages.posts.title')
        ->translateHeading()
        ->columns([
            Column::make('title')->label('refilament::messages.posts.title')->translateLabel(),
        ]);

    $payload = $table->toArray();

    expect($payload['heading'])->toBe('Post title');
    expect($payload['columns'][0]['label'])->toBe('Post title');
});

it('serializes the translated label on a form field payload', function () {
    $component = TextInput::make('title')
        ->label('refilament::messages.posts.title')
        ->translateLabel();

    expect($component->toArray()['label'])->toBe('Post title');
});

it('resolves a label under a shipped locale bundle', function () {
    Lang::setLocale('es');
    app()->setLocale('es');

    $column = Column::make('title')->label('refilament::tables.filters.trashed.with_trashed')->translateLabel();

    // The package ships an es bundle derived from Filament's corpus, so the
    // translator resolves it in-place rather than falling back to English.
    expect($column->getLabel())->toBe('Con registros eliminados');
    expect(app()->getLocale())->toBe('es');

    app()->setLocale('en');
});

it('translates an action label when enabled, else verbatim', function () {
    $action = Action::make('edit')->label('refilament::messages.posts.title')->translateLabel();

    expect($action->getLabel())->toBe('Post title');

    $verbatim = Action::make('edit')->label('refilament::messages.posts.title');

    expect($verbatim->getLabel())->toBe('refilament::messages.posts.title');
});

it('translates a bulk action label when enabled', function () {
    $action = BulkAction::make('publish')->label('refilament::messages.posts.title')->translateLabel();

    expect($action->getLabel())->toBe('Post title');
});

it('translates filter labels when enabled', function () {
    $select = SelectFilter::make('status')->label('refilament::messages.posts.title')->translateLabel();
    $text = TextFilter::make('q')->label('refilament::messages.posts.title')->translateLabel();
    $trashed = TrashedFilter::make()->label('refilament::messages.posts.title')->translateLabel();

    expect($select->getLabel())->toBe('Post title');
    expect($text->getLabel())->toBe('Post title');
    expect($trashed->getLabel())->toBe('Post title');
});

it('translates a filter label on its serialized payload', function () {
    $text = TextFilter::make('q')->label('refilament::messages.posts.title')->translateLabel();

    expect($text->toArray()['label'])->toBe('Post title');
});

it('translates a table group label when enabled', function () {
    $group = Group::make('status')->label('refilament::messages.posts.title')->translateLabel();

    expect($group->getLabel())->toBe('Post title');
});

it('translates a tab label when enabled', function () {
    $tab = Tab::make('refilament::messages.posts.title')->translateLabel();

    expect($tab->getLabel())->toBe('Post title');
});

it('translates navigation group and item labels when enabled', function () {
    $group = NavigationGroup::make('refilament::messages.posts.title')->translateLabel();
    $item = NavigationItem::make('refilament::messages.posts.title')->translateLabel();

    expect($group->getLabel())->toBe('Post title');
    expect($item->getLabel())->toBe('Post title');
    expect($group->toArray()['label'])->toBe('Post title');
    expect($item->toArray()['label'])->toBe('Post title');
});

it('translates a widget stat label when enabled', function () {
    $stat = Stat::make('refilament::messages.posts.title', 12)->translateLabel();

    expect($stat->getLabel())->toBe('Post title');
});

it('resolves package-owned defaults under Arabic from the shipped corpus', function () {
    Lang::setLocale('ar');
    app()->setLocale('ar');

    // The trashed filter options and restore/force-delete bulk action labels
    // are package-owned defaults wired to the `refilament::` namespace, which
    // ships an Arabic bundle derived from Filament's corpus. Each must resolve
    // to a real (non-empty, non-key) Arabic string rather than fall through.
    $trashed = app('translator')->get('refilament::tables.filters.trashed.with_trashed');
    $restore = app('translator')->get('refilament::actions.restore.multiple.label');
    $forceDelete = app('translator')->get('refilament::actions.force-delete.multiple.label');

    expect($trashed)->not->toBe('refilament::tables.filters.trashed.with_trashed')
        ->not->toBeEmpty();
    expect($restore)->not->toBe('refilament::actions.restore.multiple.label')
        ->not->toBeEmpty();
    expect($forceDelete)->not->toBe('refilament::actions.force-delete.multiple.label')
        ->not->toBeEmpty();

    app()->setLocale('en');
});
