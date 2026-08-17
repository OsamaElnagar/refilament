<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Enums;

/**
 * The named icon sizes a catalog can be asked for (mirrors Filament's
 * `IconSize`). Values match the `fi-size-{value}` hook classes Filament uses
 * on its rendered icon wrapper; here they are carried through the contract
 * signature so a `ScalableIcon` can decide its output per size.
 */
enum IconSize: string
{
    case ExtraSmall = 'xs';

    case Small = 'sm';

    case Medium = 'md';

    case Large = 'lg';

    case ExtraLarge = 'xl';

    case TwoExtraLarge = '2xl';

    /**
     * @deprecated Use `TwoExtraLarge` instead.
     */
    public const self ExtraExtraLarge = self::TwoExtraLarge;
}
