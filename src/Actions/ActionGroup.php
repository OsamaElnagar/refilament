<?php

declare(strict_types=1);

namespace Refilament\Refilament\Actions;

use BackedEnum;
use Refilament\Refilament\Support\Icons\IconManager;

/**
 * A dropdown group of row actions (professional actions slice — docs/ROADMAP.md
 * "2.5 Table & bulk actions"). Mirrors Filament's ActionGroup: a group renders
 * as one overflow trigger (an ellipsis by default) whose menu holds the member
 * actions that are visible for the record.
 *
 * Groups serialize as a single entry in the table's `actions` list — `{ name,
 * label, icon?, group: true, items: [<serialized actions>] }` — and a row
 * carries the group's name among its visible actions when at least one member
 * is visible for that record. The action endpoints resolve members by name, so
 * a grouped action is triggered exactly like a flat one.
 */
class ActionGroup
{
    /** @var array<int, Action> */
    protected array $actions = [];

    protected ?string $label = null;

    protected ?string $icon = null;

    /** @param  array<int, Action>  $actions */
    public function __construct(array $actions = [])
    {
        $this->actions($actions);
    }

    /**
     * @param  array<int, Action>|Action  $actions
     * @return self The return type is deliberately `self`: ActionGroup is not
     *              subclassed the way built-in actions are (no per-class
     *              defaults to override), so construction is always the base
     *              class.
     */
    public static function make(array|Action $actions = []): self
    {
        return new self(is_array($actions) ? $actions : [$actions]);
    }

    /**
     * @param  array<int, Action>|Action  $actions
     */
    public function actions(array|Action $actions): static
    {
        $this->actions = array_merge($this->actions, is_array($actions) ? $actions : [$actions]);

        return $this;
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string|BackedEnum|null $icon): static
    {
        $this->icon = IconManager::normalize($icon);

        return $this;
    }

    /**
     * @return array<int, Action>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    public function getLabel(): string
    {
        return $this->label ?? 'Actions';
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * The name this group is known by on the wire — its label's slug, kept
     * stable so the row's visible-action names stay readable and
     * deterministic across requests.
     */
    public function getName(): string
    {
        $name = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $this->getLabel()), '-'));

        return $name === '' ? 'actions' : $name;
    }

    /**
     * Whether any member action is visible for the record — the group renders
     * (and its name rides on the row) exactly then.
     */
    public function isVisibleFor(object $record): bool
    {
        foreach ($this->actions as $action) {
            if ($action->isVisibleFor($record)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a member action by name (the action endpoints search groups).
     */
    public function findAction(string $name): ?Action
    {
        foreach ($this->actions as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Serialize the group for the table payload — one entry with the member
     * actions as `items`. Members are serialized whole (visibility is
     * per-record and resolved at row time; the React runtime filters the
     * group's items for the record in hand).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'group' => true,
            'items' => array_map(static fn (Action $action): array => $action->toArray(), $this->actions),
            ...($this->icon !== null ? ['icon' => $this->icon] : []),
        ];
    }
}
