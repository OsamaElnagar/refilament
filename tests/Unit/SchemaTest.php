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
                demoComponentForSchema('title')->required()->validation(['min:3', 'max:255']),
                demoComponentForSchema('slug')->validation(['required', 'regex:/^[a-z0-9-]+$/']),
            ]),
            demoComponentForSchema('author')->required(),
        ]),
        demoComponentForSchema('plain'),
    ]);

    expect($schema->getValidationRules())->toBe([
        'title' => ['min:3', 'max:255', 'required'],
        'slug' => ['required', 'regex:/^[a-z0-9-]+$/'],
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

it('keeps the required rule when validation() replaces rules', function () {
    $component = demoComponentForSchema('title')
        ->required()
        ->validation(['string', 'max:255']);

    expect($component->getValidationRules())->toBe(['string', 'max:255', 'required']);
    expect($component->isRequired())->toBeTrue();
});
