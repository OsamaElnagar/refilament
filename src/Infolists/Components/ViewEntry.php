<?php

declare(strict_types=1);

namespace Refilament\Refilament\Infolists\Components;

use Refilament\Refilament\Schemas\Components\Component;

/**
 * View entry — an embedded React component inside a read-only infolist
 * (slice 3.9 / docs/PLAN_COLUMNS_INFOLISTS.md §2).
 *
 *     ViewEntry::make('stats-card')->viewData(['total' => 42])
 *
 * The infolist equivalent of `Schemas\Components\View` in the form tree: `view`
 * is the key the client resolves through its view-component registry
 * (`registerViewComponent()`), and `viewData` is the server-computed props the
 * component receives. Closures never survive serialization. Mirrors how a
 * Filament `ViewEntry` renders a named embedded view.
 */
class ViewEntry extends Component
{
    protected ?string $view = null;

    /** @var array<string, mixed> */
    protected array $viewData = [];

    public static function make(?string $view = null): static
    {
        // The base make() forwards its argument to the (final) constructor
        // that sets the name — ViewEntry's argument is a component key, so the
        // fluent factory sets it directly.
        $component = new static;

        if ($view !== null) {
            $component->view($view);
        }

        return $component;
    }

    public function getType(): string
    {
        return 'view_entry';
    }

    public function view(?string $view): static
    {
        $this->view = $view;

        return $this;
    }

    public function getView(): ?string
    {
        return $this->view;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function viewData(array $data): static
    {
        $this->viewData = $data;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return $this->viewData;
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'view' => $this->view,
            'viewData' => $this->viewData !== [] ? $this->viewData : null,
        ]);
    }
}
