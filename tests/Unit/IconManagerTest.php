<?php

declare(strict_types=1);

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Actions\BulkAction;
use Refilament\Refilament\Facades\RefilamentIcon;
use Refilament\Refilament\Support\Icons\Heroicon;
use Refilament\Refilament\Support\Icons\IconManager;
use Refilament\Refilament\Widgets\StatsOverviewWidget\Stat;

beforeEach(function (): void {
    $this->manager = new IconManager;
});

describe('registering icons', function (): void {
    it('can register a string icon alias', function (): void {
        $this->manager->register([
            'tables::bulk-action.delete' => 'trash',
        ]);

        expect($this->manager->resolve('tables::bulk-action.delete'))->toBe('trash');
    });

    it('can register a `BackedEnum` icon alias', function (): void {
        $this->manager->register([
            'tables::bulk-action.delete' => Heroicon::Trash,
        ]);

        expect($this->manager->resolve('tables::bulk-action.delete'))->toBe(Heroicon::Trash);
    });

    it('merges icons across multiple `register()` calls', function (): void {
        $this->manager->register(['icon-a' => 'check']);
        $this->manager->register(['icon-b' => 'x']);

        expect($this->manager->resolve('icon-a'))->toBe('check');
        expect($this->manager->resolve('icon-b'))->toBe('x');
    });

    it('overwrites an existing alias on re-registration', function (): void {
        $this->manager->register(['icon' => 'check']);
        $this->manager->register(['icon' => 'x']);

        expect($this->manager->resolve('icon'))->toBe('x');
    });
});

describe('resolving icons', function (): void {
    it('returns `null` for an unregistered alias', function (): void {
        expect($this->manager->resolve('nonexistent'))->toBeNull();
    });

    it('resolves from an array of aliases, returning the first match', function (): void {
        $this->manager->register([
            'icon-b' => Heroicon::Star,
        ]);

        expect($this->manager->resolve(['icon-a', 'icon-b', 'icon-c']))->toBe(Heroicon::Star);
    });

    it('returns `null` when no alias in the array matches', function (): void {
        expect($this->manager->resolve(['nonexistent-a', 'nonexistent-b']))->toBeNull();
    });
});

describe('service wiring', function (): void {
    it('resolves the manager from the container', function (): void {
        expect(app(IconManager::class))->toBeInstanceOf(IconManager::class);
    });

    it('seeds known canonical keys from the `Heroicon` catalog', function (): void {
        expect(app(IconManager::class)->resolve('check'))->toBe(Heroicon::Check);
        expect(app(IconManager::class)->resolve('o-check'))->toBe(Heroicon::OutlinedCheck);
    });

    it('resolves through the `RefilamentIcon` facade', function (): void {
        expect(RefilamentIcon::resolve('trash'))->toBe(Heroicon::Trash);
        expect(RefilamentIcon::resolve('missing'))->toBeNull();
    });
});

describe('`Heroicon` enums in icon builders', function (): void {
    it('`IconManager::normalize()` collapses an enum to its canonical key', function (): void {
        expect(IconManager::normalize(Heroicon::CheckCircle))->toBe('check-circle');
        expect(IconManager::normalize('x'))->toBe('x');
        expect(IconManager::normalize(null))->toBeNull();
    });

    it('accepts a `Heroicon` case on an `Action`', function (): void {
        expect(Action::make('a')->icon(Heroicon::CheckCircle)->getIcon())->toBe('check-circle');
    });

    it('accepts a `Heroicon` case on a `BulkAction`', function (): void {
        expect(BulkAction::make('b')->icon(Heroicon::Archive)->getIcon())->toBe('archive');
    });

    it('serializes a `Heroicon` case on a `Stat` to the canonical key', function (): void {
        $payload = Stat::make('Label', 1)->icon(Heroicon::ChartBar)->toArray();

        expect($payload['icon'])->toBe('chart-bar');
    });

    it('keeps a plain string icon untouched', function (): void {
        expect(Action::make('a')->icon('check-circle')->getIcon())->toBe('check-circle');
    });
});
