<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\Component;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Support\ComponentManager;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Summarizers\Sum;
use Refilament\Refilament\Tables\Summarizers\Summarizer;
use Refilament\Refilament\Tables\Table;

beforeEach(function () {
    // Registered defaults are global state (mirroring Filament's live
    // component manager). Flush before AND after: Pest runs test files in
    // one process, so a default left by this file's last test would leak
    // into the next file's assertions (e.g. a plain Table::make() suddenly
    // carrying a default sort). Macros are NOT flushed here — the workbench
    // provider registers the demo `egp` macro at boot for PostsTable, and
    // this file's macro tests re-register the same names (idempotent).
    ComponentManager::flush();
});

afterEach(function () {
    ComponentManager::flush();
});

it('applies a configureUsing default to every instance at construction', function () {
    Table::configureUsing(fn (Table $table): Table => $table->defaultSort('published_at', 'desc'));

    $table = Table::make();

    expect($table->getDefaultSortColumn())->toBe('published_at');
    expect($table->getDefaultSortDirection())->toBe('desc');
});

it('lets per-instance calls win over global defaults', function () {
    Table::configureUsing(fn (Table $table): Table => $table->defaultSort('created_at', 'desc'));

    $table = Table::make()->defaultSort('title', 'asc');

    expect($table->getDefaultSortColumn())->toBe('title');
    expect($table->getDefaultSortDirection())->toBe('asc');
});

it('applies parent-class defaults to subclasses via the hierarchy walk', function () {
    // The production-reference §1.1 lever: register on the abstract parent
    // and every concrete subclass inherits the default.
    Summarizer::configureUsing(fn (Summarizer $summarizer): Summarizer => $summarizer->label('Grand total'));

    $sum = Sum::make('views');

    expect($sum->getLabel())->toBe('Grand total');
});

it('applies component-wide defaults to concrete fields', function () {
    Component::configureUsing(fn (Component $component): Component => $component->maxLength(140));

    $field = TextInput::make('bio');

    expect($field->toArray()['maxLength'])->toBe(140);
});

it('lets important defaults win over normal defaults, but still lose to per-instance calls', function () {
    Table::configureUsing(fn (Table $table): Table => $table->defaultSort('created_at', 'desc'));
    Table::configureUsing(fn (Table $table): Table => $table->defaultSort('title', 'asc'), isImportant: true);

    expect(Table::make()->getDefaultSortColumn())->toBe('title');

    $explicit = Table::make()->defaultSort('id', 'desc');

    expect($explicit->getDefaultSortColumn())->toBe('id');
});

it('scopes a default to the $during closure and unregisters it afterwards', function () {
    $column = Table::configureUsing(
        fn (Table $table): Table => $table->defaultSort('created_at', 'desc'),
        during: function (): ?string {
            return Table::make()->getDefaultSortColumn();
        },
    );

    expect($column)->toBe('created_at');
    expect(Table::make()->getDefaultSortColumn())->toBeNull();
});

it('returns a teardown closure that unregisters the default', function () {
    $teardown = Table::configureUsing(fn (Table $table): Table => $table->defaultSort('created_at', 'desc'));

    expect(Table::make()->getDefaultSortColumn())->toBe('created_at');

    $teardown();

    expect(Table::make()->getDefaultSortColumn())->toBeNull();
});

it('registers a macro as a first-class fluent verb on a builder', function () {
    // The production-reference §1.2 pattern — Heaven's `->egp()` money macro.
    Column::macro('egp', fn (): Column => $this->money('EGP', divideBy: 1));

    $column = Column::make('price')->egp();

    $record = new class
    {
        public function getAttribute(string $key): mixed
        {
            return 1234.5;
        }
    };

    expect($column->getStateFor($record))->toContain('1,234');
});

it('registers macros on the summarizer family', function () {
    Sum::macro('egp', fn (): Sum => $this->money('EGP', locale: 'en', decimalPlaces: 0));

    expect(Sum::hasMacro('egp'))->toBeTrue();
});

it('throws a BadMethodCallException for an unregistered method', function () {
    expect(fn () => Column::make('title')->notAMacro())->toThrow(BadMethodCallException::class);
});
