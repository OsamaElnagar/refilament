<?php

declare(strict_types=1);

namespace Refilament\Refilament\Resources\Pages;

use Closure;
use Illuminate\Routing\Route;

/**
 * A page's slot in a resource's getPages() map (slice 1.6) — mirrors
 * filament-source/panels/src/Resources/Pages/PageRegistration.php.
 *
 * Holds the page class plus the closure that registers its route. The
 * closure receives the fully-qualified route name and returns the Route —
 * the package calls registerRoute() once per entry at boot (see the service
 * provider), so no app code ever wires page routes by hand.
 */
class PageRegistration
{
    /**
     * @param  class-string  $page
     * @param  Closure(string): Route  $route
     */
    public function __construct(
        protected string $page,
        protected Closure $route,
        protected ?string $path = null,
    ) {}

    public function registerRoute(string $routeName): Route
    {
        return ($this->route)($routeName);
    }

    /**
     * @return class-string
     */
    public function getPage(): string
    {
        return $this->page;
    }

    /**
     * The URI path this page registers under (e.g. '/stats'). Used at boot
     * to detect resources that declare the same page name with conflicting
     * paths — a silent-shadow bug the service provider turns into a loud
     * LogicException (docs/ROADMAP.md, "1.6 Page system").
     */
    public function getPath(): ?string
    {
        return $this->path;
    }
}
