<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Widgets\TableWidget;
use Workbench\App\Models\Post;

it('serializes a table widget node with the first page embedded', function () {
    Post::factory()->count(6)->create();

    $widget = new class extends TableWidget
    {
        public static function table(Table $table): Table
        {
            return $table
                ->query(Post::query())
                ->recordsPerPage(5)
                ->defaultSort('created_at', 'desc')
                ->columns([
                    Column::make('title')->label('Title')->sortable(),
                    Column::make('views')->label('Views')->sortable(),
                ]);
        }

        public function getWidgetId(): string
        {
            return 'recent-posts';
        }
    };

    $node = $widget->heading('Recent posts')->toArray();

    expect($node['type'])->toBe('table');
    expect($node['id'])->toBe('recent-posts');
    expect($node['heading'])->toBe('Recent posts');
    expect($node['table']['id'])->toBe('recent-posts');
    expect($node['table']['columns'])->toHaveCount(2);
    expect($node['table']['rows'])->toHaveCount(5);
    expect($node['table']['total'])->toBe(6);
    expect($node['table']['lastPage'])->toBe(2);
});

it('keeps an explicitly configured table id', function () {
    Post::factory()->count(2)->create();

    $widget = new class extends TableWidget
    {
        public static function table(Table $table): Table
        {
            return $table
                ->id('custom-table')
                ->query(Post::query())
                ->columns([Column::make('title')->label('Title')->sortable()]);
        }
    };

    $node = $widget->toArray();

    expect($node['id'])->toBe('custom-table');
    expect($node['table']['id'])->toBe('custom-table');
});

it('omits the heading when unset', function () {
    Post::factory()->count(2)->create();

    $widget = new class extends TableWidget
    {
        public static function table(Table $table): Table
        {
            return $table->query(Post::query())->columns([Column::make('title')->label('Title')]);
        }

        public function getWidgetId(): string
        {
            return 'recent-posts';
        }
    };

    expect($widget->toArray())->not->toHaveKey('heading');
});
