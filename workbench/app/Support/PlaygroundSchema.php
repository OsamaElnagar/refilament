<?php

declare(strict_types=1);

namespace Workbench\App\Support;

use Refilament\Refilament\Schemas\Components\Checkbox;
use Refilament\Refilament\Schemas\Components\ColorPicker;
use Refilament\Refilament\Schemas\Components\DatePicker;
use Refilament\Refilament\Schemas\Components\DateTimePicker;
use Refilament\Refilament\Schemas\Components\Fieldset;
use Refilament\Refilament\Schemas\Components\FileUpload;
use Refilament\Refilament\Schemas\Components\Grid;
use Refilament\Refilament\Schemas\Components\KeyValue;
use Refilament\Refilament\Schemas\Components\Radio;
use Refilament\Refilament\Schemas\Components\Repeater;
use Refilament\Refilament\Schemas\Components\RichEditor;
use Refilament\Refilament\Schemas\Components\Section;
use Refilament\Refilament\Schemas\Components\Select;
use Refilament\Refilament\Schemas\Components\Tab;
use Refilament\Refilament\Schemas\Components\Tabs;
use Refilament\Refilament\Schemas\Components\TagsInput;
use Refilament\Refilament\Schemas\Components\Textarea;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Components\TimePicker;
use Refilament\Refilament\Schemas\Components\Toggle;
use Refilament\Refilament\Schemas\Components\ToggleButtons;
use Refilament\Refilament\Schemas\Components\View;
use Refilament\Refilament\Schemas\Components\Wizard;
use Refilament\Refilament\Schemas\Components\WizardStep;
use Refilament\Refilament\Schemas\Schema;

final class PlaygroundSchema
{
    /**
     * Demo field values, keyed by field name (docs/CONTRACT.md).
     *
     * @return array<string, int|string|bool|float|array<array-key, mixed>|null>
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

            'product_name' => '',
            'product_price' => '',
            'shipping_city' => '',
            'shipping_zip' => '',
            'agree_terms' => false,

            'team_members' => [
                ['name' => '', 'role' => ''],
            ],

            'attachment' => '',
            'body' => '',

            'launch_date' => '2025-06-15',
            'launch_time' => '10:30',
            'published_at' => '2025-06-15 10:30:00',
            'closing_date' => '',

            'tags' => ['laravel', 'inertia'],

            'priority' => 'normal',
            'labels' => ['featured', 'hot'],

            'accent_color' => '#6366f1',
            'danger_color' => 'rgba(239, 68, 68, 0.8)',

            'meta' => [
                ['key' => 'theme', 'value' => 'dark'],
                ['key' => 'language', 'value' => 'en'],
            ],

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
                                ->rules(['unique:posts,slug']),

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
                                ->rules(['string', 'max:500'])
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
                            ->rules(['required', 'in:public,members,private']),

                        Grid::make()->columns(3)->schema([
                            TextInput::make('publish_date')
                                ->label('Publish date')
                                ->type('date')
                                ->rules(['date', 'nullable']),

                            TextInput::make('publish_time')
                                ->label('Publish time')
                                ->type('time')
                                ->rules(['date_format:H:i', 'nullable']),

                            TextInput::make('reading_time')
                                ->label('Reading time (min)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(120)
                                ->step(5)
                                ->placeholder('10')
                                ->rules(['numeric', 'min:0', 'max:120']),
                        ]),
                    ]),

                Section::make()
                    ->heading('Date & time pickers')
                    ->description('The React date/time picker family — calendar, time steppers, min/max bounds (slice: date/time picker)')
                    ->schema([
                        Grid::make()->columns(2)->schema([
                            DateTimePicker::make('published_at')
                                ->label('Published at')
                                ->helperText('Date + time with seconds')
                                ->minDate('2025-01-01')
                                ->maxDate('2025-12-31'),

                            DatePicker::make('launch_date')
                                ->label('Launch date')
                                ->helperText('Date only')
                                ->weekStartsOnSunday(),

                            TimePicker::make('launch_time')
                                ->label('Launch time')
                                ->helperText('Time only (HH:MM)')
                                ->seconds(false)
                                ->minutesStep(5)
                                ->closeOnDateSelection(),

                            DatePicker::make('closing_date')
                                ->label('Closing date')
                                ->helperText('A disabled range example')
                                ->minDate('2025-06-01')
                                ->disabledDates(['2025-06-10', '2025-06-11']),
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
                                ->rules(['numeric', 'nullable']),

                            TextInput::make('vat_amount')
                                ->label('VAT (14%)')
                                ->numeric()
                                ->readOnly()
                                ->placeholder('—')
                                ->computed('subtotal * 0.14')
                                ->rules(['numeric', 'nullable']),

                            TextInput::make('total_amount')
                                ->label('Total')
                                ->numeric()
                                ->readOnly()
                                ->placeholder('—')
                                ->computed('subtotal + vat_amount')
                                ->rules(['numeric', 'nullable'])
                                ->columnSpan(2),
                        ]),
                    ]),

                Section::make()
                    ->heading('Wizard layout')
                    ->description('Multi-step form via the wizard container (next/back navigation, one step at a time)')
                    ->schema([
                        Wizard::make()
                            ->startOnStep(1)
                            ->steps([
                                WizardStep::make('Details')
                                    ->description('The product basics')
                                    ->schema([
                                        TextInput::make('product_name')
                                            ->label('Product name')
                                            ->placeholder('Mechanical keyboard')
                                            ->rules(['required', 'string']),
                                        TextInput::make('product_price')
                                            ->label('Price (USD)')
                                            ->integer()
                                            ->placeholder('129')
                                            ->rules(['required', 'integer', 'min:0']),
                                    ]),
                                WizardStep::make('Shipping')
                                    ->description('Where it goes')
                                    ->schema([
                                        TextInput::make('shipping_city')
                                            ->label('City')
                                            ->placeholder('Cairo')
                                            ->rules(['required', 'string']),
                                        TextInput::make('shipping_zip')
                                            ->label('ZIP')
                                            ->placeholder('11511')
                                            ->rules(['required', 'string']),
                                    ]),
                                WizardStep::make('Review')
                                    ->description('Confirm before saving')
                                    ->schema([
                                        Toggle::make('agree_terms')
                                            ->label('I agree to the terms')
                                            ->default(false),
                                    ]),
                            ]),
                    ]),

                Section::make()
                    ->heading('Embedded React view')
                    ->description('A bespoke React component inside the form tree (the embedded-React slice)')
                    ->schema([
                        View::make('playground-callout')
                            ->viewData([
                                'title' => 'Embedded React component',
                                'body' => 'This callout is a consumer-registered React component rendered by a View::make() node — the server names the key and supplies the props, the client renders the component. Nothing about it lives in the form runtime.',
                            ]),
                    ]),

                Section::make()
                    ->heading('Repeater')
                    ->description('A repeatable group of fields — each row a mini form (add/remove, collapsible, reorder, clone, numbers, dynamic labels)')
                    ->schema([
                        Repeater::make('team_members')
                            ->label('Team members')
                            ->helperText('One row per team member; every row validates against the row rules on submit.')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->maxItems(5)
                            ->collapsible()
                            ->grid(2)
                            ->addActionLabel('Add member')
                            ->itemLabel('Member')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Name')
                                    ->placeholder('Ada Lovelace')
                                    ->rules(['required', 'string', 'max:255']),
                                TextInput::make('role')
                                    ->label('Role')
                                    ->placeholder('Engineer')
                                    ->rules(['required', 'string', 'max:255']),
                            ]),

                        Repeater::make('expenses')
                            ->label('Expenses')
                            ->helperText('Drag rows to reorder, clone a row, and watch the heading follow the description field.')
                            ->defaultItems(2)
                            ->minItems(1)
                            ->maxItems(8)
                            ->collapsible()
                            ->collapsed()
                            ->cloneable()
                            ->reorderableWithButtons()
                            ->itemNumbers()
                            ->itemLabel('Expense {description}')
                            ->grid(2)
                            ->schema([
                                TextInput::make('description')
                                    ->label('Description')
                                    ->placeholder('Team lunch')
                                    ->rules(['required', 'string', 'max:255']),
                                TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->rules(['required', 'numeric', 'min:0']),
                            ]),

                        Repeater::make('skills')
                            ->label('Skills (read-only rows)')
                            ->helperText('Addable and reorderable, but rows cannot be deleted or cloned.')
                            ->addActionLabel('Add skill')
                            ->addable()
                            ->deletable(false)
                            ->cloneable(false)
                            ->itemNumbers()
                            ->schema([
                                TextInput::make('skill')
                                    ->label('Skill')
                                    ->placeholder('Laravel'),
                            ]),
                    ]),

                Section::make()
                    ->heading('Tags')
                    ->description('A tags input — add with Enter/comma, remove, drag to reorder, click suggestions')
                    ->schema([
                        TagsInput::make('tags')
                            ->label('Tags')
                            ->helperText('Press Enter or comma to add a tag')
                            ->placeholder('Add a tag…')
                            ->splitKeys(['Enter', ','])
                            ->suggestions(['laravel', 'inertia', 'react', 'tailwind', 'livewire'])
                            ->tagPrefix('#')
                            ->reorderable(),
                    ]),

                Section::make()
                    ->heading('Toggle buttons')
                    ->description('A segmented button group — single or multiple select, inline/grouped, per-option colors and icons')
                    ->schema([
                        Grid::make()->columns(2)->schema([
                            ToggleButtons::make('priority')
                                ->label('Priority')
                                ->helperText('Single-select segmented control')
                                ->default('normal')
                                ->options([
                                    'low' => 'Low',
                                    'normal' => 'Normal',
                                    'high' => 'High',
                                    'urgent' => 'Urgent',
                                ])
                                ->colors([
                                    'low' => 'gray',
                                    'normal' => 'info',
                                    'high' => 'warning',
                                    'urgent' => 'danger',
                                ]),

                            ToggleButtons::make('labels')
                                ->label('Labels')
                                ->helperText('Multi-select — toggles each option')
                                ->multiple()
                                ->grouped()
                                ->icons(['featured' => 'star', 'hot' => 'alert'])
                                ->options([
                                    'featured' => 'Featured',
                                    'hot' => 'Hot',
                                    'pinned' => 'Pinned',
                                ])
                                ->colors(['pinned' => 'success']),

                            ToggleButtons::make('approved')
                                ->label('Approved')
                                ->helperText('The boolean() preset — success/danger Yes/No')
                                ->boolean()
                                ->columnSpan(2),
                        ]),
                    ]),

                Section::make()
                    ->heading('Color picker')
                    ->description('A text input with a popover picker — hue/lightness square, hue and alpha sliders, presets; value serialized in the chosen format')
                    ->schema([
                        Grid::make()->columns(2)->schema([
                            ColorPicker::make('accent_color')
                                ->label('Accent color')
                                ->helperText('Serialized as hex')
                                ->default('#6366f1'),

                            ColorPicker::make('danger_color')
                                ->label('Danger color')
                                ->helperText('Serialized as rgba — note the alpha slider')
                                ->rgba(),
                        ]),
                    ]),

                Section::make()
                    ->heading('Key value')
                    ->description('An editable table of key/value rows — add, remove, drag to reorder')
                    ->schema([
                        KeyValue::make('meta')
                            ->label('Metadata')
                            ->helperText('Each row stores a key/value pair')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->keyPlaceholder('e.g. theme')
                            ->valuePlaceholder('e.g. dark')
                            ->addActionLabel('Add a pair')
                            ->reorderable(),
                    ]),

                Section::make()
                    ->heading('Media & rich text')
                    ->description('File upload through the typed upload endpoint, plus a rich HTML editor')
                    ->schema([
                        FileUpload::make('attachment')
                            ->label('Attachment')
                            ->disk('public')
                            ->directory('playground')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'application/pdf'])
                            ->maxSize(2048)
                            ->helperText('Files store to the public disk through POST /refilament/upload; the payload carries the stored path.'),

                        RichEditor::make('body')
                            ->label('Body')
                            ->placeholder('Write something…')
                            ->maxHeight(240)
                            ->helperText('A contenteditable HTML editor — the value is the raw HTML string.'),
                    ]),

                Wizard::make()
                    ->skippable()
                    ->startOnStep(1)
                    ->steps([
                        WizardStep::make('Basics')
                            ->description('Post details')
                            ->icon('pencil')
                            ->schema([
                                TextInput::make('wizard_title')
                                    ->label('Title')
                                    ->placeholder('My post title')
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('wizard_summary')
                                    ->label('Summary')
                                    ->placeholder('A short summary…')
                                    ->rows(3),
                            ]),

                        WizardStep::make('Publishing')
                            ->description('Visibility & timing')
                            ->icon('archive')
                            ->schema([
                                Select::make('wizard_status')
                                    ->label('Status')
                                    ->placeholder('Pick a status')
                                    ->default('draft')
                                    ->options([
                                        'draft' => 'Draft',
                                        'published' => 'Published',
                                        'archived' => 'Archived',
                                    ]),

                                Toggle::make('wizard_featured')
                                    ->label('Featured')
                                    ->default(false)
                                    ->helperText('Show in the featured slot'),
                            ]),
                    ]),
            ]);
    }
}
