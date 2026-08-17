<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Embedded view component (mirrors `Filament\Schemas\Components\View`) — the
 * "embedded React in custom pages" slice. Anywhere a field or layout can
 * live (a page form, an infolist, a section), a `View::make($key)` node
 * renders a consumer-registered React component instead: `view` is the
 * component key the client resolves through its view-component registry, and
 * `viewData` is the server-computed props the component receives. Closures
 * never survive serialization — resolve viewData() from plain values or
 * build it per request in the schema resolver.
 *
 * The contract node is `{ type: 'view', view, viewData? }`; the client
 * falls back to a neutral placeholder when the key isn't registered, so a
 * missing consumer component never breaks the page.
 */
class View extends Component
{
    protected ?string $view = null;

    /**
     * @var array<string, mixed>
     */
    protected array $viewData = [];

    public static function make(?string $view = null): static
    {
        // The base make() forwards its argument to the (final) constructor
        // that sets the name — View's argument is a component key, so the
        // fluent factory sets it directly.
        $component = new static;

        if ($view !== null) {
            $component->view($view);
        }

        return $component;
    }

    public function getType(): string
    {
        return 'view';
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
