<?php

declare(strict_types=1);

use LogicException;
use Refilament\Refilament\Schemas\Components\Component;
use Refilament\Refilament\Schemas\Components\Grid;
use Refilament\Refilament\Schemas\Components\Section;
use Refilament\Refilament\Schemas\Schema;

function demoComponentForSchema(?string $name = null): Component
{
    return new class($name) extends Component
    {
        public function getType(): string
        {
            return 'text_input';
        }
    };
}

it('serializes the contract envelope', function () {
    $document = Schema::make()
        ->components([
            demoComponentForSchema('title'),
            demoComponentForSchema('slug'),
        ])
        ->toArray();

    expect($document)->toBe([
        'contract' => Schema::CONTRACT_VERSION,
        'schema' => [
            ['type' => 'text_input', 'name' => 'title', 'label' => 'Title'],
            ['type' => 'text_input', 'name' => 'slug', 'label' => 'Slug'],
        ],
    ]);
});

it('accepts a single component', function () {
    $schema = Schema::make()->components(demoComponentForSchema('title'));

    expect($schema->getComponents())->toHaveCount(1);
});

it('appends components across calls', function () {
    $schema = Schema::make()
        ->components(demoComponentForSchema('title'))
        ->components(demoComponentForSchema('slug'));

    expect($schema->getComponents())->toHaveCount(2);
});

it('collects validation rules from every field in the tree', function () {
    $schema = Schema::make()->components([
        Section::make()->schema([
            Grid::make()->columns(2)->schema([
                demoComponentForSchema('title')->required()->rules(['min:3', 'max:255']),
                demoComponentForSchema('slug')->rules(['required', 'regex:/^[a-z0-9-]+$/']),
            ]),
            demoComponentForSchema('author')->required(),
        ]),
        demoComponentForSchema('plain'),
    ]);

    expect($schema->getValidationRules())->toBe([
        'title' => ['required', 'min:3', 'max:255'],
        'slug' => ['nullable', 'required', 'regex:/^[a-z0-9-]+$/'],
        'author' => ['required'],
    ]);
});

it('never validates hidden fields', function () {
    $schema = Schema::make()->components([
        demoComponentForSchema('visible')->required(),
        demoComponentForSchema('secret')->required()->hidden(),
    ]);

    expect($schema->getValidationRules())->toBe(['visible' => ['required']]);
});

it('never validates dehydrated(false) fields — shown but never submitted', function () {
    $schema = Schema::make()->components([
        demoComponentForSchema('visible')->required(),
        demoComponentForSchema('computed')->required()->dehydrated(false),
    ]);

    expect($schema->getValidationRules())->toBe(['visible' => ['required']]);
});

it('serializes readOnly and dehydrated flags on the field node', function () {
    $node = demoComponentForSchema('total')
        ->readOnly()
        ->dehydrated(false)
        ->toArray();

    expect($node['readOnly'])->toBeTrue();
    expect($node['dehydrated'])->toBeFalse();
    expect(array_key_exists('disabled', $node))->toBeFalse();

    // Defaults: a plain field is dehydrated and not read-only — both keys
    // stay absent from the contract.
    $plain = demoComponentForSchema('total')->toArray();

    expect(array_key_exists('readOnly', $plain))->toBeFalse();
    expect(array_key_exists('dehydrated', $plain))->toBeFalse();
});

it('hides fields for a named operation via hiddenOn()', function () {
    $document = Schema::make()->components([
        demoComponentForSchema('slug')->hiddenOn('edit'),
    ])->toArray('edit');

    expect($document['schema'][0]['hidden'])->toBeTrue();
});

it('keeps hiddenOn fields visible for other operations', function () {
    $document = Schema::make()->components([
        demoComponentForSchema('slug')->hiddenOn('edit'),
    ])->toArray('create');

    expect(array_key_exists('hidden', $document['schema'][0]))->toBeFalse();
});

it('disables fields for a named operation via disabledOn()', function () {
    $document = Schema::make()->components([
        demoComponentForSchema('code')->disabledOn('edit'),
    ])->toArray('edit');

    expect($document['schema'][0]['disabled'])->toBeTrue();

    $create = Schema::make()->components([
        demoComponentForSchema('code')->disabledOn('edit'),
    ])->toArray('create');

    expect(array_key_exists('disabled', $create['schema'][0]))->toBeFalse();
});

it('never validates fields hidden for the current operation', function () {
    $schema = Schema::make()->components([
        demoComponentForSchema('slug')->required()->hiddenOn('create'),
    ]);

    expect($schema->getValidationRules('create'))->toBe([]);
    expect($schema->getValidationRules('edit'))->toBe(['slug' => ['required']]);
});

it('carries operation-aware rules through layout children', function () {
    $document = Schema::make()->components([
        Section::make()->schema([demoComponentForSchema('secret')->hiddenOn('edit')]),
    ])->toArray('edit');

    expect($document['schema'][0]['schema'][0]['hidden'])->toBeTrue();
});

it('maps field names to labels for validator attributes', function () {
    $schema = Schema::make()->components([
        demoComponentForSchema('first_name')->label('First Name')->required(),
        demoComponentForSchema('secret')->hidden(),
    ]);

    expect($schema->getValidationAttributes())->toBe(['first_name' => 'First Name']);
});

it('runs the submit handler with the validated data', function () {
    $received = null;

    $schema = Schema::make()
        ->submitUsing(static function (array $data) use (&$received): void {
            $received = $data;
        })
        ->successMessage('Saved.');

    $schema->submit(['title' => 'Hello']);

    expect($received)->toBe(['title' => 'Hello']);
    expect($schema->getSuccessMessage())->toBe('Saved.');
});

it('throws when submitted without a handler', function () {
    Schema::make()->submit([]);
})->throws(LogicException::class, 'must have a [submitUsing()] handler');

it('keeps the required rule when rules() are set', function () {
    $component = demoComponentForSchema('title')
        ->required()
        ->rules(['string', 'max:255']);

    expect($component->getValidationRules())->toBe(['required', 'string', 'max:255']);
    expect($component->isRequired())->toBeTrue();
});
