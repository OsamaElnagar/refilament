<?php

declare(strict_types=1);

use Refilament\Refilament\Support\Concerns\EvaluatesClosures;
use Refilament\Refilament\Support\Concerns\HasAlignment;
use Refilament\Refilament\Support\Concerns\HasBadge;
use Refilament\Refilament\Support\Concerns\HasBadgeTooltip;
use Refilament\Refilament\Support\Concerns\HasColor;
use Refilament\Refilament\Support\Concerns\HasExtraAttributes;
use Refilament\Refilament\Support\Concerns\HasFontFamily;
use Refilament\Refilament\Support\Concerns\HasIcon;
use Refilament\Refilament\Support\Concerns\HasIconColor;
use Refilament\Refilament\Support\Concerns\HasIconPosition;
use Refilament\Refilament\Support\Concerns\HasIconSize;
use Refilament\Refilament\Support\Concerns\HasLineClamp;
use Refilament\Refilament\Support\Concerns\HasPlaceholder;
use Refilament\Refilament\Support\Concerns\HasTooltip;
use Refilament\Refilament\Support\Concerns\HasWeight;
use Refilament\Refilament\Support\Concerns\HasWidth;
use Refilament\Refilament\Support\Enums\Alignment;
use Refilament\Refilament\Support\Enums\FontWeight;
use Refilament\Refilament\Support\Enums\IconPosition;
use Refilament\Refilament\Support\Enums\IconSize;

beforeEach(function (): void {
    $this->component = new class
    {
        use EvaluatesClosures;
        use HasAlignment;
        use HasBadge;
        use HasBadgeTooltip;
        use HasColor;
        use HasExtraAttributes;
        use HasFontFamily;
        use HasIcon;
        use HasIconColor;
        use HasIconPosition;
        use HasIconSize;
        use HasLineClamp;
        use HasPlaceholder;
        use HasTooltip;
        use HasWeight;
        use HasWidth;
    };
});

it('returns null for unset config and false for `has*()` guards', function (): void {
    expect($this->component->getColor())->toBeNull()
        ->and($this->component->getIcon())->toBeNull()
        ->and($this->component->getTooltip())->toBeNull()
        ->and($this->component->hasColor())->toBeFalse()
        ->and($this->component->hasIcon())->toBeFalse()
        ->and($this->component->hasTooltip())->toBeFalse()
        ->and($this->component->hasBadge())->toBeFalse();
});

it('stores and evaluates a color', function (): void {
    $this->component->color('red');

    expect($this->component->getColor())->toBe('red')
        ->and($this->component->hasColor())->toBeTrue();

    $this->component->color(fn (): string => 'blue');

    expect($this->component->getColor())->toBe('blue');
});

it('uses `defaultColor()` only when no color is set', function (): void {
    $this->component->defaultColor('gray');

    expect($this->component->getColor())->toBe('gray');

    $this->component->color('primary');

    expect($this->component->getColor())->toBe('primary');
});

it('stores and evaluates a badge with a color', function (): void {
    $this->component->badge(3);
    $this->component->badgeColor('green');

    expect($this->component->getBadge())->toBe('3')
        ->and($this->component->getBadgeColor('3'))->toBe('green')
        ->and($this->component->hasBadge())->toBeTrue();
});

it('keeps `indicator()` as a deprecated alias of `badge()`', function (): void {
    $this->component->indicator('New');

    expect($this->component->getBadge())->toBe('New');
});

it('stores an icon and an icon color / position / size', function (): void {
    $this->component->icon('check');
    $this->component->iconColor('green');
    $this->component->iconPosition(IconPosition::After);
    $this->component->iconSize(IconSize::Large);

    expect($this->component->getIcon())->toBe('check')
        ->and($this->component->getIconColor())->toBe('green')
        ->and($this->component->getIconPosition())->toBe(IconPosition::After)
        ->and($this->component->getIconSize())->toBe(IconSize::Large)
        ->and($this->component->hasIcon())->toBeTrue();
});

it('defaults the icon position to before', function (): void {
    expect($this->component->getIconPosition())->toBe(IconPosition::Before);
});

it('can disable an icon with null', function (): void {
    $this->component->icon('check');
    $this->component->icon(null);

    expect($this->component->getIcon())->toBeNull()
        ->and($this->component->hasIcon())->toBeFalse();
});

it('resolves alignment and exposes shorthand helpers', function (): void {
    expect($this->component->getAlignment())->toBeNull();

    $this->component->alignCenter();

    expect($this->component->getAlignment())->toBe(Alignment::Center)
        ->and($this->component->hasDynamicAlignment())->toBeTrue();

    $this->component->alignment('end');

    expect($this->component->getAlignment())->toBe(Alignment::End);
});

it('resolves weight and font family from a string', function (): void {
    $this->component->weight('bold');

    expect($this->component->getWeight())->toBe(FontWeight::Bold);

    $this->component->weight(fn (): string => 'extrabold');

    expect($this->component->getWeight())->toBe(FontWeight::ExtraBold);
});

it('appends px to an integer width', function (): void {
    $this->component->width(320);

    expect($this->component->getWidth())->toBe('320px');

    $this->component->width('full');

    expect($this->component->getWidth())->toBe('full');
});

it('stores a line clamp and placeholder', function (): void {
    $this->component->lineClamp(2);
    $this->component->placeholder('Select…');

    expect($this->component->getLineClamp())->toBe(2)
        ->and($this->component->hasLineClamp())->toBeTrue()
        ->and($this->component->getPlaceholder())->toBe('Select…');
});

it('stores a tooltip and badge tooltip', function (): void {
    $this->component->tooltip('Hint');
    $this->component->badgeTooltip('Badge hint');

    expect($this->component->getTooltip())->toBe('Hint')
        ->and($this->component->getBadgeTooltip('x'))->toBe('Badge hint')
        ->and($this->component->hasTooltip())->toBeTrue();
});

it('merges extra attributes and evaluates closures', function (): void {
    $this->component->extraAttributes(['class' => 'p-2']);
    $this->component->extraAttributes(fn (): array => ['data-x' => '1'], merge: true);

    expect($this->component->getExtraAttributes())->toBe(['class' => 'p-2', 'data-x' => '1'])
        ->and($this->component->hasExtraAttributes())->toBeTrue();
});
