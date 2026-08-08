<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\Component;
use Refilament\Refilament\Tables\Action;

function demoTextInput(?string $name = null): Component
{
    return new class($name) extends Component
    {
        public function getType(): string
        {
            return 'text_input';
        }
    };
}

function demoSelect(?string $name = null): Component
{
    return new class($name) extends Component
    {
        public function getType(): string
        {
            return 'select';
        }
    };
}

it('serializes a configured component to its contract node', function () {
    $node = demoTextInput('title')
        ->label('Title')
        ->placeholder('My post title')
        ->helperText('Shown in lists and feeds')
        ->required()
        ->validation(['required', 'max:255'])
        ->maxLength(255)
        ->columnSpan(2)
        ->toArray();

    expect($node)->toBe([
        'type' => 'text_input',
        'name' => 'title',
        'label' => 'Title',
        'placeholder' => 'My post title',
        'helperText' => 'Shown in lists and feeds',
        'required' => true,
        'validation' => ['required', 'max:255'],
        'maxLength' => 255,
        'columnSpan' => 2,
    ]);
});

it('defaults the label to a humanized version of the name', function () {
    $node = demoTextInput('first_name')->toArray();

    expect($node['label'])->toBe('First Name');
});

it('omits unset keys from the serialized node', function () {
    expect(demoTextInput('slug')->toArray())->toBe([
        'type' => 'text_input',
        'name' => 'slug',
        'label' => 'Slug',
    ]);
});

it('serializes static options into the contract list shape', function () {
    $node = demoSelect('status')
        ->default('draft')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ])
        ->toArray();

    expect($node)->toBe([
        'type' => 'select',
        'name' => 'status',
        'label' => 'Status',
        'default' => 'draft',
        'options' => [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'published', 'label' => 'Published'],
        ],
    ]);
});

it('serializes server-dependent options as a dependsOn declaration', function () {
    $node = demoSelect('state')->dependsOn(['country'])->toArray();

    expect($node['dependsOn'])->toBe(['country']);
    expect($node)->not->toHaveKey('options');
});

it('adds the required rule when required() is called', function () {
    $node = demoTextInput('title')->required()->toArray();

    expect($node['required'])->toBeTrue();
    expect($node['validation'])->toBe(['required']);
});

it('keeps required and disabled flags out of the payload when false', function () {
    $node = demoTextInput('title')->toArray();

    expect($node)->not->toHaveKey('required');
    expect($node)->not->toHaveKey('disabled');
});

it('exposes config getters', function () {
    $component = demoTextInput('title')->label('Title')->disabled();

    expect($component->getName())->toBe('title');
    expect($component->getLabel())->toBe('Title');
    expect($component->isDisabled())->toBeTrue();
    expect($component->isVisible())->toBeTrue();
});

it('serializes hint text, a hint icon and hint actions into the label row', function () {
    $node = demoTextInput('author')
        ->hint('Pick a real person')
        ->hintIcon('chart-bar', 'Shown as a badge in the table')
        ->hintActions([
            Action::make('view-authors')
                ->label('View authors')
                ->icon('document')
                ->tooltip('Open the users page in a new tab')
                ->url('/refilament/users')
                ->openUrlInNewTab()
                ->visibleWhenFilled('author'),
        ])
        ->toArray();

    expect($node['hint'])->toBe('Pick a real person');
    expect($node['hintIcon'])->toBe([
        'icon' => 'chart-bar',
        'tooltip' => 'Shown as a badge in the table',
    ]);
    expect($node['hintActions'])->toBe([
        [
            'name' => 'view-authors',
            'label' => 'View authors',
            'url' => '/refilament/users',
            'openUrlInNewTab' => true,
            'visibleWhenFilled' => ['author'],
            'icon' => 'document',
            'tooltip' => 'Open the users page in a new tab',
        ],
    ]);
});

it('omits the hint keys when unset', function () {
    $node = demoTextInput('title')->toArray();

    expect($node)->not->toHaveKey('hint');
    expect($node)->not->toHaveKey('hintIcon');
    expect($node)->not->toHaveKey('hintActions');
});
