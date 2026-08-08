<?php

declare(strict_types=1);

namespace Workbench\App\Support;

use Refilament\Refilament\Schemas\Components\Checkbox;
use Refilament\Refilament\Schemas\Components\Fieldset;
use Refilament\Refilament\Schemas\Components\Grid;
use Refilament\Refilament\Schemas\Components\Radio;
use Refilament\Refilament\Schemas\Components\Section;
use Refilament\Refilament\Schemas\Components\Select;
use Refilament\Refilament\Schemas\Components\Tab;
use Refilament\Refilament\Schemas\Components\Tabs;
use Refilament\Refilament\Schemas\Components\Textarea;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Components\Toggle;
use Refilament\Refilament\Schemas\Schema;

final class PlaygroundSchema
{
    /**
     * Demo field values, keyed by field name (docs/CONTRACT.md).
     *
     * @return array<string, int|string|null>
     */
    public static function data(): array
    {
        return [
            'title' => '',
            'slug' => '',
            'status' => 'draft',
            'password' => '',
            'country' => '',
            'state' => '',
            'excerpt' => '',
            'featured' => false,
            'allow_comments' => true,
            'visibility' => 'public',
            'publish_date' => '',
            'publish_time' => '',
            'reading_time' => '',
            'show_author' => false,
            'author_bio' => '',
            'comment_review_note' => '',

            'company' => '',
            'website' => '',
            'plan_frequency' => 'monthly',
            'plan_tier' => 'pro',

            'quantity' => 2,
            'unit_price' => 49.99,
            'subtotal' => null,
            'vat_amount' => null,
            'total_amount' => null,
        ];
    }

    public static function make(): Schema
    {
        return Schema::make()
            ->id('playground')
            ->components([
                Section::make()
                    ->heading('Post details')
                    ->description('The basics of your post')
                    ->schema([
                        Grid::make()->columns(2)->schema([
                            TextInput::make('title')
                                ->label('Title')
                                ->placeholder('My post title')
                                ->helperText('Shown in lists and feeds')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(2),

                            TextInput::make('slug')
                                ->label('Slug')
                                ->placeholder('my-post-title')
                                ->helperText('Checked live against existing posts as you type')
                                ->validation(['unique:posts,slug']),

                            Select::make('status')
                                ->label('Status')
                                ->placeholder('Pick a status')
                                ->searchable()
                                ->default('draft')
                                ->options([
                                    'draft' => 'Draft',
                                    'published' => 'Published',
                                    'archived' => 'Archived',
                                ]),
                        ]),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(),
                    ]),

                Section::make()
                    ->heading('Location')
                    ->description('State options depend on the country')
                    ->schema([
                        Grid::make()->columns(2)->schema([
                            Select::make('country')
                                ->label('Country')
                                ->searchable()
                                ->options([
                                    'us' => 'United States',
                                    'gb' => 'United Kingdom',
                                    'ca' => 'Canada',
                                ]),

                            Select::make('state')
                                ->label('State / Province')
                                ->searchable()
                                ->placeholder('Pick a state')
                                ->dependsOn(['country'])
                                ->resolveOptionsUsing(static function (array $data): array {
                                    return match ($data['country'] ?? '') {
                                        'us' => [
                                            'al' => 'Alabama',
                                            'ca' => 'California',
                                            'ny' => 'New York',
                                        ],
                                        'gb' => [
                                            'eng' => 'England',
                                            'sct' => 'Scotland',
                                            'wls' => 'Wales',
                                        ],
                                        'ca' => [
                                            'on' => 'Ontario',
                                            'qc' => 'Quebec',
                                            'bc' => 'British Columbia',
                                        ],
                                        default => [],
                                    };
                                }),
                        ]),
                    ]),

                Section::make()
                    ->heading('Publishing')
                    ->description('Textarea plus the boolean fields — checkbox and toggle (slice 1.4)')
                    ->schema([
                        Grid::make()->columns(2)->schema([
                            Textarea::make('excerpt')
                                ->label('Excerpt')
                                ->placeholder('A short summary…')
                                ->helperText('Shown in lists and feeds')
                                ->rows(4)
                                ->maxLength(500)
                                ->validation(['string', 'max:500'])
                                ->columnSpan(2),

                            Checkbox::make('featured')
                                ->label('Featured post')
                                ->helperText('Highlight this post on the home page')
                                ->inline(),

                            Toggle::make('allow_comments')
                                ->label('Allow comments')
                                ->helperText('Readers can leave comments')
                                ->inline()
                                ->default(true),
                        ]),
                    ]),

                Section::make()
                    ->heading('Publication')
                    ->description('Radio group plus native date/time/number inputs (slice 1.5)')
                    ->schema([
                        Radio::make('visibility')
                            ->label('Visibility')
                            ->helperText('Who can see this post')
                            ->inline()
                            ->default('public')
                            ->options([
                                'public' => 'Public',
                                'members' => 'Members only',
                                'private' => 'Private',
                            ])
                            ->validation(['required', 'in:public,members,private']),

                        Grid::make()->columns(3)->schema([
                            TextInput::make('publish_date')
                                ->label('Publish date')
                                ->type('date')
                                ->validation(['date', 'nullable']),

                            TextInput::make('publish_time')
                                ->label('Publish time')
                                ->type('time')
                                ->validation(['date_format:H:i', 'nullable']),

                            TextInput::make('reading_time')
                                ->label('Reading time (min)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(120)
                                ->step(5)
                                ->placeholder('10')
                                ->validation(['numeric', 'min:0', 'max:120']),
                        ]),
                    ]),

                Section::make()
                    ->heading('Visibility rules')
                    ->description('Sibling fields shown/hidden by client-side rules (slice 2.4)')
                    ->schema([
                        Grid::make()->columns(2)->schema([
                            Toggle::make('show_author')
                                ->label('Show author box')
                                ->helperText('Reveals the author bio below')
                                ->inline()
                                ->default(false),

                            Textarea::make('author_bio')
                                ->label('Author bio')
                                ->placeholder('A short biography…')
                                ->rows(3)
                                ->helperText('Visible only while "Show author box" is on')
                                ->whenTruthy('show_author'),

                            TextInput::make('comment_review_note')
                                ->label('Comment review note')
                                ->placeholder('Why moderate comments?')
                                ->helperText('Visible only while "Allow comments" is off')
                                ->whenFalsy('allow_comments'),
                        ]),
                    ]),

                Section::make()
                    ->heading('Fieldset + Tabs layouts')
                    ->description('Groups via the fieldset and tab containers (slice 2.6)')
                    ->schema([
                        Fieldset::make('Billing address')
                            ->columns(2)
                            ->schema([
                                TextInput::make('company')->label('Company')->placeholder('Acme Inc.'),
                                TextInput::make('website')->label('Website')->placeholder('acme.example'),
                            ]),

                        Tabs::make()
                            ->activeTab(1)
                            ->tabs([
                                Tab::make('Billing')
                                    ->schema([
                                        Select::make('plan_frequency')
                                            ->label('Billing frequency')
                                            ->options([
                                                'monthly' => 'Monthly',
                                                'yearly' => 'Yearly',
                                            ])
                                            ->default('monthly'),
                                    ]),
                                Tab::make('Plan')
                                    ->schema([
                                        Radio::make('plan_tier')
                                            ->label('Plan tier')
                                            ->options([
                                                'basic' => 'Basic',
                                                'pro' => 'Pro',
                                                'enterprise' => 'Enterprise',
                                            ])
                                            ->default('pro'),
                                    ]),
                            ]),
                    ]),

                Section::make()
                    ->heading('Invoice arithmetic')
                    ->description('Computed fields — live client-side totals, no round trip (slice C3)')
                    ->schema([
                        Grid::make()->columns(2)->schema([
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->minValue(0)
                                ->default(2)
                                ->helperText('Editable — drives the computed fields'),

                            TextInput::make('unit_price')
                                ->label('Unit price')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->default(49.99)
                                ->helperText('Editable — drives the computed fields'),

                            TextInput::make('subtotal')
                                ->label('Subtotal')
                                ->numeric()
                                ->readOnly()
                                ->placeholder('—')
                                // Computed fields (slice C3): the Ahram
                                // `->numeric()->readOnly()->dehydrated()`
                                // idiom without the Livewire machinery — the
                                // expression is serialized as data and
                                // evaluated client-side as you type.
                                ->computed('quantity * unit_price')
                                ->validation(['numeric', 'nullable']),

                            TextInput::make('vat_amount')
                                ->label('VAT (14%)')
                                ->numeric()
                                ->readOnly()
                                ->placeholder('—')
                                ->computed('subtotal * 0.14')
                                ->validation(['numeric', 'nullable']),

                            TextInput::make('total_amount')
                                ->label('Total')
                                ->numeric()
                                ->readOnly()
                                ->placeholder('—')
                                ->computed('subtotal + vat_amount')
                                ->validation(['numeric', 'nullable'])
                                ->columnSpan(2),
                        ]),
                    ]),
            ]);
    }
}
