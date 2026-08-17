<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\Fieldset;
use Refilament\Refilament\Schemas\Components\Tab;
use Refilament\Refilament\Schemas\Components\Tabs;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Components\View;
use Refilament\Refilament\Schemas\Components\Wizard;
use Refilament\Refilament\Schemas\Components\WizardStep;

it('serializes a fieldset with label and columns', function () {
    $node = Fieldset::make('Billing')
        ->columns(3)
        ->schema([
            TextInput::make('company'),
            TextInput::make('website'),
        ])
        ->toArray();

    expect($node['type'])->toBe('fieldset');
    expect($node['label'])->toBe('Billing');
    expect($node['columns'])->toBe(3);
    expect($node['schema'][0]['name'])->toBe('company');
    expect($node['schema'][1]['name'])->toBe('website');
});

it('defaults a fieldset to a 2-column grid', function () {
    $node = Fieldset::make('Billing')->schema([TextInput::make('a')])->toArray();

    expect($node['columns'])->toBe(2);
});

it('clamps fieldset columns to at least 1', function () {
    $node = Fieldset::make('X')->columns(0)->toArray();

    expect($node['columns'])->toBe(1);
});

it('omits a fieldset label when not set', function () {
    $node = Fieldset::make()->schema([TextInput::make('a')])->toArray();

    expect($node)->not->toHaveKey('label');
});

it('serializes tabs with their children', function () {
    $node = Tabs::make()
        ->activeTab(2)
        ->tabs([
            Tab::make('Billing')->schema([TextInput::make('company')]),
            Tab::make('Plan')->schema([TextInput::make('plan_tier')]),
        ])
        ->toArray();

    expect($node['type'])->toBe('tabs');
    expect($node['activeTab'])->toBe(2);
    expect($node['schema'])->toHaveCount(2);
    expect($node['schema'][0]['type'])->toBe('tab');
    expect($node['schema'][0]['label'])->toBe('Billing');
    expect($node['schema'][0]['schema'][0]['name'])->toBe('company');
});

it('omits the default activeTab', function () {
    $node = Tabs::make()->tabs([Tab::make('A')])->toArray();

    expect($node)->not->toHaveKey('activeTab');
});

it('rejects non-Tab children in tabs', function () {
    Tabs::make()->tabs([TextInput::make('nope')]);
})->throws(LogicException::class, 'must be instances of');

it('serializes a wizard with its steps and children', function () {
    $node = Wizard::make()
        ->startOnStep(2)
        ->steps([
            WizardStep::make('Details')->schema([TextInput::make('name')]),
            WizardStep::make('Shipping')
                ->description('Where it goes')
                ->icon('truck')
                ->schema([TextInput::make('city')]),
        ])
        ->toArray();

    expect($node['type'])->toBe('wizard');
    expect($node['startOnStep'])->toBe(2);
    expect($node['schema'])->toHaveCount(2);
    expect($node['schema'][0]['type'])->toBe('wizard-step');
    expect($node['schema'][0]['label'])->toBe('Details');
    expect($node['schema'][0]['schema'][0]['name'])->toBe('name');
    expect($node['schema'][1]['description'])->toBe('Where it goes');
    expect($node['schema'][1]['icon'])->toBe('truck');
});

it('omits the default wizard options and serializes skippable', function () {
    $defaults = Wizard::make()->steps([WizardStep::make('A')])->toArray();

    expect($defaults)->not->toHaveKey('startOnStep')
        ->and($defaults)->not->toHaveKey('skippable');

    $skippable = Wizard::make()->skippable()->steps([WizardStep::make('A')])->toArray();

    expect($skippable['skippable'])->toBeTrue();
});

it('rejects non-step children in a wizard', function () {
    Wizard::make()->steps([TextInput::make('nope')]);
})->throws(LogicException::class, 'must be instances of');

it('serializes a standalone wizard step', function () {
    $node = Tab::make('Billing')->schema([TextInput::make('company')])->toArray();

    expect($node['type'])->toBe('tab');
    expect($node['label'])->toBe('Billing');
    expect($node['schema'][0]['name'])->toBe('company');
});

it('omits a tab label when not set', function () {
    $node = Tab::make()->schema([TextInput::make('a')])->toArray();

    expect($node)->not->toHaveKey('label');
});

it('serializes an embedded view node with its data', function () {
    $node = View::make('stats-card')
        ->viewData(['total' => 42, 'label' => 'Posts'])
        ->toArray();

    expect($node['type'])->toBe('view');
    expect($node['view'])->toBe('stats-card');
    expect($node['viewData'])->toBe(['total' => 42, 'label' => 'Posts']);
});

it('omits viewData when empty', function () {
    $node = View::make('stats-card')->toArray();

    expect($node['view'])->toBe('stats-card')
        ->and($node)->not->toHaveKey('viewData');
});
