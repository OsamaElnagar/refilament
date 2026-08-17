<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas;

use Illuminate\Support\Arr;

/**
 * A stateless reader over a form's data snapshot, typed into rule and
 * condition closures (`fn (Get $get) => $get('type') === 'physical'`).
 * Mirrors Filament's `Filament\Forms\Get` — but where Filament's Get reads
 * the persistent Livewire component's live state, ours reads the data of a
 * single request: submitted values at validation time, initial values at
 * serialization time. Closures never re-execute across requests; each
 * request evaluates them against its own snapshot (docs/ARCHITECTURE.md,
 * "Reactivity").
 *
 * Supports dot notation for nested paths, so repeater row fields can read
 * their siblings: `$get('specs.0.name')`.
 */
class Get
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(protected array $data) {}

    public function __invoke(string $name): mixed
    {
        return Arr::get($this->data, $name);
    }
}
