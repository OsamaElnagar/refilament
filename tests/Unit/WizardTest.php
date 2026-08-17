<?php

declare(strict_types=1);

use LogicException;
use Refilament\Refilament\Schemas\Components\Grid;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Components\Wizard;
use Refilament\Refilament\Schemas\Components\WizardStep;

it('returns the wizard type', function () {
    expect(Wizard::make()->getType())->toBe('wizard');
});

it('serializes steps with their label, description, icon and schema', function () {
    $node = Wizard::make()->steps([
        WizardStep::make('Basics')
            ->description('Post details')
            ->icon('pencil')
            ->schema([
                TextInput::make('title')->label('Title'),
            ]),
        WizardStep::make('Publishing')
            ->schema([
                TextInput::make('status')->label('Status'),
            ]),
    ])->toArray();

    expect($node['type'])->toBe('wizard');
    expect($node['schema'])->toBe([
        [
            'type' => 'wizard-step',
            'label' => 'Basics',
            'description' => 'Post details',
            'icon' => 'pencil',
            'schema' => [
                ['type' => 'text_input', 'name' => 'title', 'label' => 'Title'],
            ],
        ],
        [
            'type' => 'wizard-step',
            'label' => 'Publishing',
            'schema' => [
                ['type' => 'text_input', 'name' => 'status', 'label' => 'Status'],
            ],
        ],
    ]);
});

it('omits startOnStep and skippable at their defaults', function () {
    $node = Wizard::make()->steps([
        WizardStep::make('Basics')->schema([TextInput::make('title')]),
    ])->toArray();

    expect($node)->not->toHaveKey('startOnStep');
    expect($node)->not->toHaveKey('skippable');
});

it('serializes startOnStep only when it differs from one', function () {
    expect(Wizard::make()->startOnStep(2)->toArray()['startOnStep'])->toBe(2);
    expect(Wizard::make()->startOnStep(1)->toArray())->not->toHaveKey('startOnStep');
});

it('clamps startOnStep to a minimum of one', function () {
    expect(Wizard::make()->startOnStep(0)->getStartOnStep())->toBe(1);
});

it('serializes skippable only when enabled', function () {
    expect(Wizard::make()->skippable()->toArray()['skippable'])->toBeTrue();
    expect(Wizard::make()->toArray())->not->toHaveKey('skippable');
});

it('rejects non-step children', function () {
    expect(fn () => Wizard::make()->steps([Grid::make()]))
        ->toThrow(LogicException::class);
});

it('defaults a step label to an empty string', function () {
    expect(WizardStep::make()->getLabel())->toBe('');
});
