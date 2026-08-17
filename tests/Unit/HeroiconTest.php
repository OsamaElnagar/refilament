<?php

declare(strict_types=1);

use Refilament\Refilament\Support\Enums\IconSize;
use Refilament\Refilament\Support\Icons\Heroicon;

it('is a string-backed enum', function (): void {
    expect(Heroicon::Check->value)->toBe('check');
});

it('has outlined icon variants with an `o-` prefix', function (): void {
    expect(Heroicon::OutlinedCheck->value)->toBe('o-check');
});

describe('`getIconForSize()`', function (): void {
    it('returns the canonical key for a plain case', function (): void {
        $icon = Heroicon::Check;

        expect($icon->getIconForSize(IconSize::Medium))->toBe('check');
        expect($icon->getIconForSize(IconSize::Large))->toBe('check');
    });

    it('strips the `o-` prefix for outlined cases', function (): void {
        $icon = Heroicon::OutlinedCheck;

        expect($icon->getIconForSize(IconSize::Small))->toBe('check');
        expect($icon->getIconForSize(IconSize::Medium))->toBe('check');
    });

    it('is size-independent for the canonical key', function (): void {
        expect(Heroicon::Trash->getIconForSize(IconSize::ExtraSmall))->toBe('trash');
        expect(Heroicon::Trash->getIconForSize(IconSize::TwoExtraLarge))->toBe('trash');
    });

    it('matches the renderer-known keys end to end', function (): void {
        $expected = [
            Heroicon::CheckCircle,
            Heroicon::X,
            Heroicon::XCircle,
            Heroicon::Globe,
            Heroicon::Mail,
            Heroicon::Phone,
            Heroicon::User,
            Heroicon::Users,
            Heroicon::Link,
            Heroicon::Star,
            Heroicon::Clock,
            Heroicon::Lock,
            Heroicon::Pencil,
            Heroicon::Trash,
            Heroicon::MoreHorizontal,
            Heroicon::Archive,
            Heroicon::Eye,
            Heroicon::EyeOff,
            Heroicon::Pin,
            Heroicon::Alert,
            Heroicon::Tag,
            Heroicon::Plus,
            Heroicon::ChartBar,
            Heroicon::Document,
            Heroicon::ExternalLink,
            Heroicon::Package,
            Heroicon::Settings,
        ];

        foreach ($expected as $icon) {
            expect($icon->getIconForSize(IconSize::Medium))->toBe($icon->value);
        }
    });
});

it('`IconSize` exposes Filament-compatible sizes', function (): void {
    expect(IconSize::Medium->value)->toBe('md');
    expect(IconSize::TwoExtraLarge->value)->toBe('2xl');
});
