<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Enums;

/**
 * Where the table's filters render (mirrors Filament's FiltersLayout enum).
 *
 *   - Dropdown (default) — filters in a dropdown panel anchored to a toolbar
 *     trigger button that carries the active-filter count.
 *   - Modal — the same trigger, but the filters open in a modal dialog.
 *   - AboveContent — a filter row above the table content.
 *   - AboveContentCollapsible — the above-content row, collapsed by default
 *     and toggled by the toolbar trigger.
 *   - BelowContent — a filter row below the table content (after pagination).
 *   - BeforeContent / AfterContent — a filter column to the left / right of
 *     the table.
 *   - BeforeContentCollapsible / AfterContentCollapsible — the side columns,
 *     collapsed by default and toggled by the toolbar trigger.
 *   - Hidden — no filter UI renders; active filter indicators still show.
 */
enum FiltersLayout: string
{
    case AboveContent = 'above-content';

    case AboveContentCollapsible = 'above-content-collapsible';

    case BelowContent = 'below-content';

    case BeforeContent = 'before-content';

    case AfterContent = 'after-content';

    case BeforeContentCollapsible = 'before-content-collapsible';

    case AfterContentCollapsible = 'after-content-collapsible';

    case Dropdown = 'dropdown';

    case Modal = 'modal';

    case Hidden = 'hidden';
}
