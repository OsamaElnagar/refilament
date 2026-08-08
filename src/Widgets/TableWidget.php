<?php

declare(strict_types=1);

namespace Refilament\Refilament\Widgets;

use Refilament\Refilament\Tables\Table;

/**
 * Table widget (slice D1) — the Ahram idiom of a widget that is itself a
 * table (`RecentSalesInvoicesTable`): a reusable table class hosted inside a
 * widget card. The widget serializes the table's first page as its node; the
 * React runtime renders it with TableRenderer, whose sorting and pagination
 * reuse the typed table endpoint — so the widget's table must also be
 * resolvable by its id server-side (Refilament::registerTable) for
 * interactions to work. The embedded snapshot always renders.
 *
 * `table()` follows the same reusable-class convention as Resource::table():
 * build a Table with a query() (and typically columns / sort / page size),
 * nothing else. A subclass is one of the panel's widgets and doubles as a
 * table definition — one class, two contexts, exactly like Ahram.
 */
abstract class TableWidget extends Widget
{
    protected ?string $heading = null;

    /**
     * Build the widget's table. Must set a `query()`; columns, default sort
     * and page size are typical. Rows come from the typed table endpoint when
     * the client interacts, and from the embedded snapshot on first render.
     */
    abstract public static function table(Table $table): Table;

    public function heading(?string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    /**
     * The widget's table, bound to the widget's id when the class doesn't set
     * one — the id is both the widget key and the typed table endpoint's
     * address, so a registered widget table needs no extra configuration.
     */
    public function getWidgetTable(): Table
    {
        $table = static::table(Table::make());

        if ($table->getId() === null) {
            $table->id(static::getWidgetId());
        }

        return $table;
    }

    public function getJsonType(): string
    {
        return 'table';
    }

    public function toArray(): array
    {
        $table = $this->getWidgetTable();

        return [
            ...parent::toArray(),
            'id' => $table->getId(),
            ...($this->heading !== null ? ['heading' => $this->heading] : []),
            'table' => $table->toPayload(),
        ];
    }
}
