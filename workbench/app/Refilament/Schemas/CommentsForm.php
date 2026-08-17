<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Schemas;

use Refilament\Refilament\Schemas\Components\Textarea;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Components\Toggle;
use Refilament\Refilament\Schemas\Schema;

/**
 * The comments form schema — a standalone class, like PostsForm, so the
 * relation manager's create/edit modals (a later slice) compose it verbatim.
 */
class CommentsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->id('comment-form')
            ->components([
                TextInput::make('title')
                    ->label('Title')
                    ->rules(['required', 'string', 'max:255'])
                    ->required(),

                Textarea::make('content')
                    ->label('Content')
                    ->rules(['required', 'string'])
                    ->required(),

                Toggle::make('is_visible')
                    ->label('Public visibility')
                    ->default(true),
            ]);
    }
}
