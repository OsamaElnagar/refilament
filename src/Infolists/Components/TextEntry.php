<?php

declare(strict_types=1);

namespace Refilament\Refilament\Infolists\Components;

/**
 * Text entry — the workhorse of a read-only record display (slice 3.3). A
 * labeled value rendered from the bound record, with the full formatting and
 * display surface (money / date / numeric / badge / color / icon / url).
 *
 *     TextEntry::make('status')->badge()->color(fn ($state) => ...)
 *
 * Mirrors `Filament\Infolists\Components\TextEntry`.
 */
class TextEntry extends Entry
{
    public function getType(): string
    {
        return 'text_entry';
    }
}
