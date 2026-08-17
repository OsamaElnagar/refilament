<?php

declare(strict_types=1);

use Illuminate\Validation\Rule as ValidationRule;
use Refilament\Refilament\Schemas\Components\Component;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Get;
use Refilament\Refilament\Schemas\Schema;

function rulesTestComponent(?string $name = null): Component
{
    return new class($name) extends Component
    {
        public function getType(): string
        {
            return 'text_input';
        }
    };
}

it('appends rules across calls instead of replacing', function () {
    $component = rulesTestComponent('title')
        ->rules(['string'])
        ->rules(['max:255']);

    expect($component->getValidationRules())->toBe(['nullable', 'string', 'max:255']);
});

it('splits a pipe-separated rules string', function () {
    $component = rulesTestComponent('title')->rules('required|string|max:255');

    expect($component->getValidationRules())->toBe(['nullable', 'required', 'string', 'max:255']);
});

it('evaluates a rules closure against the data snapshot', function () {
    $component = rulesTestComponent('title')
        ->rules(fn (Get $get): array => $get('status') === 'published' ? ['string', 'max:255'] : []);

    expect($component->getValidationRules())->toBe([]);

    $component->setValidationData(['status' => 'published']);

    expect($component->getValidationRules())->toBe(['nullable', 'string', 'max:255']);
});

it('evaluates a conditional required closure against the data snapshot', function () {
    $component = rulesTestComponent('sku')
        ->required(fn (Get $get): bool => $get('type') === 'physical');

    expect($component->isRequired())->toBeFalse();
    expect($component->getValidationRules())->toBe([]);

    $component->setValidationData(['type' => 'physical']);

    expect($component->isRequired())->toBeTrue();
    expect($component->getValidationRules())->toBe(['required']);
});

it('passes a Laravel closure rule through untouched', function () {
    $closureRule = function (string $attribute, mixed $value, Closure $fail): void {
        if ($value === 'forbidden') {
            $fail('The :attribute is forbidden.');
        }
    };

    $component = rulesTestComponent('name')->rule($closureRule);

    expect($component->getValidationRules())->toBe(['nullable', $closureRule]);
});

it('passes a Rule object through untouched', function () {
    $rule = ValidationRule::in(['draft', 'published']);

    $component = rulesTestComponent('status')->rule($rule);

    expect($component->getValidationRules())->toBe(['nullable', $rule]);
});

it('gates rules with a boolean condition', function () {
    $component = rulesTestComponent('title')->rules(['max:255'], false);

    expect($component->getValidationRules())->toBe([]);
});

it('gates rules with a condition closure evaluated against the data', function () {
    $component = rulesTestComponent('title')
        ->rules(['max:255'], fn (Get $get): bool => $get('strict') === true);

    expect($component->getValidationRules())->toBe([]);

    $component->setValidationData(['strict' => true]);

    expect($component->getValidationRules())->toBe(['nullable', 'max:255']);
});

it('never duplicates the required rule', function () {
    $component = rulesTestComponent('title')->required()->rules(['required', 'string']);

    expect($component->getValidationRules())->toBe(['required', 'string']);
});

it('serializes only the string subset of rules', function () {
    $node = rulesTestComponent('status')
        ->rules(['string'])
        ->rule(ValidationRule::in(['draft', 'published']))
        ->rule(static function (string $attribute, mixed $value, Closure $fail): void {})
        ->toArray();

    expect($node['validation'])->toBe(['string']);
    expect($node)->not->toHaveKey('rules');
});

it('reads nested values through Get dot notation', function () {
    $component = rulesTestComponent('qty')
        ->rules(fn (Get $get): array => $get('specs.0.name') === 'Connectivity' ? ['integer'] : []);

    $component->setValidationData([
        'specs' => [
            ['name' => 'Connectivity', 'value' => 'Bluetooth'],
        ],
    ]);

    expect($component->getValidationRules())->toBe(['nullable', 'integer']);
});

it('serializes a conditional required flag against the initial data', function () {
    $schema = Schema::make()->components([
        TextInput::make('sku')->required(fn (Get $get): bool => $get('type') === 'physical'),
    ]);

    expect($schema->toArray()['schema'][0])->not->toHaveKey('required');

    $schema->setValidationData(['type' => 'physical']);

    expect($schema->toArray()['schema'][0]['required'])->toBeTrue();
});

it('evaluates schema rules against the data the controller sets', function () {
    $schema = Schema::make()->components([
        rulesTestComponent('email')->rules(fn (Get $get): array => $get('notify') === true ? ['email'] : []),
    ]);

    expect($schema->getValidationRules())->toBe([]);

    $schema->setValidationData(['notify' => true]);

    expect($schema->getValidationRules())->toBe(['email' => ['nullable', 'email']]);
});
