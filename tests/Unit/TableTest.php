<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Actions\BulkAction;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Enums\FiltersLayout;
use Refilament\Refilament\Tables\SelectFilter;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Tables\TextFilter;
use Refilament\Refilament\Tables\TrashedFilter;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('serializes a column with a derived label', function () {
    expect(Column::make('title')->toArray())->toBe([
        'name' => 'title',
        'label' => 'Title',
    ]);
});

it('serializes an explicit label and placeholder', function () {
    expect(Column::make('views')->label('Page views')->toArray())->toBe([
        'name' => 'views',
        'label' => 'Page views',
    ]);

    expect(Column::make('published_at')->placeholder('—')->toArray())->toBe([
        'name' => 'published_at',
        'label' => 'Published At',
        'placeholder' => '—',
    ]);
});

it('omits the placeholder when not set', function () {
    expect(Column::make('title')->toArray())->not->toHaveKey('placeholder');
});

it('serializes a sortable column', function () {
    expect(Column::make('title')->sortable()->toArray())->toBe([
        'name' => 'title',
        'label' => 'Title',
        'sortable' => true,
    ]);
});

it('omits sortable when the column is not sortable', function () {
    expect(Column::make('title')->toArray())->not->toHaveKey('sortable');
    expect(Column::make('title')->sortable(false)->isSortable())->toBeFalse();
});

it('serializes a searchable column', function () {
    expect(Column::make('title')->searchable()->toArray())->toBe([
        'name' => 'title',
        'label' => 'Title',
        'searchable' => true,
    ]);
});

it('omits searchable when the column is not searchable', function () {
    expect(Column::make('title')->toArray())->not->toHaveKey('searchable');
    expect(Column::make('title')->searchable(false)->isSearchable())->toBeFalse();
});

it('serializes a toggleable column', function () {
    expect(Column::make('views')->toggleable()->toArray())->toBe([
        'name' => 'views',
        'label' => 'Views',
        'toggleable' => true,
    ]);

    expect(Column::make('title')->toArray())->not->toHaveKey('toggleable');
    expect(Column::make('title')->toggleable(false)->isToggleable())->toBeFalse();
});

it('resolves cell state through the state resolver', function () {
    $post = Post::factory()->create(['views' => 42]);

    $column = Column::make('views')->getStateUsing(static fn (Post $record): int => $record->views * 2);

    expect($column->getStateFor($post))->toBe(84);
});

it('falls back to the record attribute without a resolver', function () {
    $post = Post::factory()->create(['views' => 7]);

    expect(Column::make('views')->getStateFor($post))->toBe(7);
});

it('serializes a related attribute through the state resolver', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $post = Post::factory()->create(['user_id' => $user->id]);

    $table = Table::make()
        ->columns([
            Column::make('title'),
            Column::make('user')
                ->label('User')
                ->getStateUsing(static fn (Post $record): ?string => $record->user?->name),
        ])
        ->query(Post::query()->whereKey($post->id)->with('user'));

    $payload = $table->toPayload(1, 50);

    expect($payload['rows'][0]['user'])->toBe('Ada Lovelace');
});

it('never serializes the state resolver closure', function () {
    expect(
        Column::make('user')->getStateUsing(static fn (Post $record): ?string => $record->user?->name)->toArray(),
    )->not->toHaveKey('getStateUsing');
});

it('lists the searchable columns', function () {
    $table = Table::make()->columns([
        Column::make('title')->searchable(),
        Column::make('author'),
    ]);

    expect(array_map(
        static fn (Column $column): ?string => $column->getName(),
        $table->getSearchableColumns(),
    ))->toBe(['title']);

    expect(Table::make()->columns([Column::make('title')])->getSearchableColumns())->toBe([]);
});

it('serializes a select filter', function () {
    expect(
        SelectFilter::make('status')
            ->label('Status')
            ->options(['draft' => 'Draft', 'published' => 'Published'])
            ->toArray(),
    )->toBe([
        'name' => 'status',
        'label' => 'Status',
        'type' => 'select',
        'options' => [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'published', 'label' => 'Published'],
        ],
    ]);
});

it('serializes a multiple select filter', function () {
    expect(
        SelectFilter::make('status')->options(['draft' => 'Draft'])->multiple()->toArray(),
    )->toHaveKey('multiple', true);

    expect(
        SelectFilter::make('status')->options(['draft' => 'Draft'])->toArray(),
    )->not->toHaveKey('multiple');
});

it('defaults the filter attribute to its name', function () {
    expect(SelectFilter::make('status')->getAttribute())->toBe('status');
    expect(SelectFilter::make('status')->attribute('state')->getAttribute())->toBe('state');
});

it('serializes a text filter', function () {
    expect(TextFilter::make('title')->label('Title')->toArray())->toBe([
        'name' => 'title',
        'label' => 'Title',
        'type' => 'text',
    ]);
});

it('serializes the text filter placeholder when set', function () {
    expect(TextFilter::make('title')->placeholder('Filter by title…')->toArray())->toHaveKey(
        'placeholder',
        'Filter by title…',
    );

    expect(TextFilter::make('title')->toArray())->not->toHaveKey('placeholder');
});

it('defaults the text filter attribute to its name', function () {
    expect(TextFilter::make('title')->getAttribute())->toBe('title');
    expect(TextFilter::make('title')->attribute('name')->getAttribute())->toBe('name');
});

it('applies a text filter as a LIKE containment match', function () {
    Post::factory()->create(['title' => 'Launch of the Falcon']);
    Post::factory()->create(['title' => 'Falcon heavy test flight']);
    Post::factory()->create(['title' => 'Totally unrelated']);

    $table = Table::make()
        ->columns([Column::make('title')])
        ->filters([TextFilter::make('title')])
        ->query(Post::query());

    $payload = $table->toPayload(1, 50, null, 'asc', null, ['title' => 'falcon']);

    expect(collect($payload['rows'])->pluck('title')->sort()->values()->all())->toBe([
        'Falcon heavy test flight',
        'Launch of the Falcon',
    ]);
});

it('serializes mixed filter types in the table definition', function () {
    $definition = Table::make()
        ->columns([Column::make('title')])
        ->filters([
            SelectFilter::make('status')->options(['draft' => 'Draft']),
            TextFilter::make('title'),
        ])
        ->toArray();

    expect($definition['filters'])->toBe([
        [
            'name' => 'status',
            'label' => 'status',
            'type' => 'select',
            'options' => [['value' => 'draft', 'label' => 'Draft']],
        ],
        [
            'name' => 'title',
            'label' => 'title',
            'type' => 'text',
        ],
    ]);
});

it('omits filters from the definition when none are set', function () {
    expect(Table::make()->columns([Column::make('title')])->toArray())->not->toHaveKey('filters');
});

it('defaults the filter layout to dropdown when filters are set', function () {
    $definition = Table::make()
        ->columns([Column::make('title')])
        ->filters([TextFilter::make('title')])
        ->toArray();

    expect($definition['filtersLayout'])->toBe('dropdown');
});

it('serializes the filter layout passed to filters()', function () {
    $definition = Table::make()
        ->columns([Column::make('title')])
        ->filters([TextFilter::make('title')], layout: FiltersLayout::Modal)
        ->toArray();

    expect($definition['filtersLayout'])->toBe('modal');
});

it('serializes a filter layout set via filtersLayout()', function () {
    $definition = Table::make()
        ->columns([Column::make('title')])
        ->filters([TextFilter::make('title')])
        ->filtersLayout(FiltersLayout::AboveContent)
        ->toArray();

    expect($definition['filtersLayout'])->toBe('above-content');
});

it('accepts a string filter layout value', function () {
    $table = Table::make()->filters([TextFilter::make('title')], layout: 'below-content');

    expect($table->getFiltersLayout())->toBe(FiltersLayout::BelowContent);
});

it('serializes a trashed filter with its ternary options', function () {
    expect(TrashedFilter::make('trashed')->label('Trashed')->toArray())->toBe([
        'name' => 'trashed',
        'label' => 'Trashed',
        'type' => 'trashed',
        'options' => [
            ['value' => '', 'label' => 'Without deleted records'],
            ['value' => 'with', 'label' => 'With deleted records'],
            ['value' => 'only', 'label' => 'Only deleted records'],
        ],
    ]);
});

it('uses the placeholder as the without-trashed option label', function () {
    $payload = TrashedFilter::make()->placeholder('Show all posts')->toArray();

    expect($payload['options'][0]['label'])->toBe('Show all posts');
});

it('serializes filters in the table definition', function () {
    $definition = Table::make()
        ->columns([Column::make('title')])
        ->filters([SelectFilter::make('status')->options(['draft' => 'Draft'])])
        ->toArray();

    expect($definition['filters'])->toBe([
        [
            'name' => 'status',
            'label' => 'status',
            'type' => 'select',
            'options' => [['value' => 'draft', 'label' => 'Draft']],
        ],
    ]);
});

it('serializes a relationship filter with options resolved from the relation', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $filter = SelectFilter::make('user')
        ->label('User')
        ->relationship('user', 'name')
        ->setModel(new Post);

    expect($filter->toArray())->toBe([
        'name' => 'user',
        'label' => 'User',
        'type' => 'select',
        'options' => [
            ['value' => (string) $user->id, 'label' => 'Ada Lovelace'],
        ],
    ]);
});

it('injects the table model into a relationship filter before serialization', function () {
    $user = User::factory()->create(['name' => 'Grace Hopper']);

    $definition = Table::make()
        ->columns([Column::make('title')])
        ->filters([SelectFilter::make('user')->relationship('user', 'name')->multiple()])
        ->query(Post::query())
        ->toArray();

    expect($definition['filters'][0]['options'])->toContain(
        ['value' => (string) $user->id, 'label' => 'Grace Hopper'],
    );
});

it('serializes an action definition without its closure', function () {
    $action = Action::make('delete')
        ->label('Delete')
        ->color('danger')
        ->requiresConfirmation()
        ->action(static fn (Post $post): bool => $post->delete());

    expect($action->toArray())->toBe([
        'name' => 'delete',
        'label' => 'Delete',
        'color' => 'danger',
        'requiresConfirmation' => true,
    ]);
});

it('omits optional action keys when not set', function () {
    expect(Action::make('publish')->toArray())->toBe([
        'name' => 'publish',
        'label' => 'Publish',
    ]);
});

it('runs the action closure against a record', function () {
    $post = Post::factory()->create(['status' => 'draft']);

    $action = Action::make('publish')
        ->action(static fn (Post $record): bool => $record->update(['status' => 'published']));

    expect($action->call($post))->toBeTrue();
    expect($post->fresh()->status)->toBe('published');
});

it('evaluates per-record visibility', function () {
    $published = Post::factory()->create(['status' => 'published']);
    $draft = Post::factory()->create(['status' => 'draft']);

    $action = Action::make('publish')
        ->visible(static fn (Post $record): bool => $record->status !== 'published');

    expect($action->isVisibleFor($published))->toBeFalse();
    expect($action->isVisibleFor($draft))->toBeTrue();
    expect(Action::make('delete')->isVisibleFor($published))->toBeTrue();
});

it('throws when calling an action without a closure', function () {
    Action::make('empty')->call(Post::factory()->create());
})->throws(LogicException::class);

it('serializes actions in the table definition', function () {
    $definition = Table::make()
        ->columns([Column::make('title')])
        ->actions([Action::make('delete')->label('Delete')])
        ->toArray();

    expect($definition['actions'])->toBe([
        ['name' => 'delete', 'label' => 'Delete'],
    ]);

    expect(Table::make()->columns([Column::make('title')])->toArray())->not->toHaveKey('actions');
});

it('finds an action by name', function () {
    $table = Table::make()->actions([
        Action::make('publish'),
        Action::make('delete'),
    ]);

    expect($table->findAction('delete')?->getName())->toBe('delete');
    expect($table->findAction('missing'))->toBeNull();
});

it('serializes only visible action names per record', function () {
    $draft = Post::factory()->create(['status' => 'draft']);
    $published = Post::factory()->create(['status' => 'published']);

    $table = Table::make()
        ->columns([Column::make('title')])
        ->actions([
            Action::make('publish')->visible(static fn (Post $record): bool => $record->status !== 'published'),
            Action::make('delete'),
        ])
        ->query(Post::query()->whereKey([$draft->id, $published->id]));

    $payload = $table->toPayload(1, 50);

    $rows = collect($payload['rows'])->keyBy('id');
    expect($rows[$draft->id]['actions'])->toBe(['publish', 'delete']);
    expect($rows[$published->id]['actions'])->toBe(['delete']);
});

it('resolves a record through the table query', function () {
    $post = Post::factory()->create();

    $table = Table::make()->columns([Column::make('title')])->query(Post::query());

    expect($table->findRecord((string) $post->id)?->getKey())->toBe($post->id);
    expect($table->findRecord('999999'))->toBeNull();
});

it('serializes the table definition', function () {
    $definition = Table::make()
        ->id('posts')
        ->heading('Posts')
        ->columns([Column::make('title')])
        ->recordsPerPage(25)
        ->recordsPerPageSelectOptions([25, 50])
        ->toArray();

    expect($definition)->toBe([
        'id' => 'posts',
        'columns' => [
            ['name' => 'title', 'label' => 'Title'],
        ],
        'recordsPerPage' => 25,
        'recordsPerPageSelectOptions' => [25, 50],
        'heading' => 'Posts',
    ]);
});

it('omits the heading when not set', function () {
    $definition = Table::make()->columns([Column::make('title')])->toArray();

    expect($definition)->not->toHaveKey('heading');
});

it('appends columns through repeated calls', function () {
    $table = Table::make()
        ->columns([Column::make('title')])
        ->columns([Column::make('author')]);

    expect($table->getColumns())->toHaveCount(2);
});

it('defaults to ten records per page', function () {
    expect(Table::make()->getRecordsPerPage())->toBe(10);
    expect(Table::make()->recordsPerPage(0)->getRecordsPerPage())->toBe(1);
});

it('stores the default sort', function () {
    $table = Table::make()->defaultSort('published_at', 'desc');

    expect($table->getDefaultSortColumn())->toBe('published_at');
    expect($table->getDefaultSortDirection())->toBe('desc');

    expect(Table::make()->getDefaultSortColumn())->toBeNull();
});

it('normalizes the default sort direction', function () {
    $table = Table::make()->defaultSort('title', 'something-else');

    expect($table->getDefaultSortDirection())->toBe('asc');
});

it('omits the selectable flag by default', function () {
    expect(Table::make()->columns([Column::make('title')])->toArray())->not->toHaveKey('selectable');
});

it('serializes the selectable flag when enabled', function () {
    $definition = Table::make()
        ->columns([Column::make('title')])
        ->selectable()
        ->toArray();

    expect($definition['selectable'])->toBeTrue();
    expect(Table::make()->columns([Column::make('title')])->selectable(false)->isSelectable())->toBeFalse();
});

it('serializes a bulk action definition without its closure', function () {
    $action = BulkAction::make('delete')
        ->label('Delete selected')
        ->color('danger')
        ->requiresConfirmation()
        ->action(static function (): void {});

    expect($action->toArray())->toBe([
        'name' => 'delete',
        'label' => 'Delete selected',
        'color' => 'danger',
        'requiresConfirmation' => true,
    ]);
});

it('runs the bulk action closure against the selected records', function () {
    $posts = Post::factory()->count(3)->create();
    $deleted = [];

    $action = BulkAction::make('delete')->action(static function (Collection $records) use (&$deleted): void {
        foreach ($records as $record) {
            $deleted[] = $record->id;
            $record->delete();
        }
    });

    $action->call($posts->fresh());

    expect($deleted)->toBe($posts->pluck('id')->all());
    expect(Post::count())->toBe(0);
});

it('throws when calling a bulk action without a closure', function () {
    $post = Post::factory()->create();

    BulkAction::make('empty')->call(new Collection([$post]));
})->throws(LogicException::class);

it('serializes toolbar actions in the table definition', function () {
    $definition = Table::make()
        ->columns([Column::make('title')])
        ->selectable()
        ->toolbarActions([BulkAction::make('delete')->label('Delete selected')])
        ->toArray();

    expect($definition['toolbarActions'])->toBe([
        ['name' => 'delete', 'label' => 'Delete selected'],
    ]);

    expect(Table::make()->columns([Column::make('title')])->toArray())->not->toHaveKey('toolbarActions');
});

it('omits toolbar actions when none are set', function () {
    expect(
        Table::make()->columns([Column::make('title')])->selectable()->toArray(),
    )->not->toHaveKey('toolbarActions');
});

it('finds a bulk action by name', function () {
    $table = Table::make()->toolbarActions([
        BulkAction::make('delete'),
        BulkAction::make('restore'),
    ]);

    expect($table->findBulkAction('restore')?->getName())->toBe('restore');
    expect($table->findBulkAction('missing'))->toBeNull();
});

it('resolves multiple records through the table query', function () {
    $posts = Post::factory()->count(3)->create();

    $table = Table::make()->columns([Column::make('title')])->query(Post::query());

    $records = $table->findRecords($posts->pluck('id')->all());

    expect($records->pluck('id')->sort()->values()->all())->toBe(
        $posts->pluck('id')->sort()->values()->all(),
    );
});

it('resolves trashed records for bulk actions', function () {
    $posts = Post::factory()->count(2)->create();

    $posts->each(fn ($post): mixed => $post->delete());

    $table = Table::make()->columns([Column::make('title')])->query(Post::query());

    // findRecords lifts the SoftDeletingScope so restore/forceDelete can
    // target rows that are currently trashed.
    $records = $table->findRecords($posts->pluck('id')->all());

    expect($records->pluck('id')->sort()->values()->all())->toBe(
        $posts->pluck('id')->sort()->values()->all(),
    );
});
