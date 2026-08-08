<?php

declare(strict_types=1);

namespace Refilament\Refilament\Resources\Pages;

use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Components\TextInput;

/**
 * The built-in edit page for a resource (slice 1.6, typed update 1.7) — the
 * default 'edit' slot in Resource::getPages(), served at
 * /refilament/{resource}/{record}/edit with the {record} segment constrained
 * to [0-9]+. Renders the generic refilament/resource-edit Inertia page with
 * the schema document pre-filled from the record (password-typed fields
 * always '' — the stored hash never leaves the server) plus the record key.
 *
 * The full-page edit submits through the typed record update endpoint
 * (POST /refilament/table/{resource}/record/{record}, slice 1.7) — validated
 * against the form's rules with the unique rule ignoring the record, so the
 * page no longer depends on the table declaring an edit action.
 */
class EditRecord extends Page
{
    public static function getInertiaComponent(): string
    {
        return 'refilament/resource-edit';
    }

    public static function getPayload(string $resource, Refilament $refilament, ?string $record = null): array
    {
        $class = $refilament->getResourceClass($resource);

        if ($class === null) {
            abort(404);
        }

        $schema = $refilament->resolveSchema($class::getFormId());

        if ($schema === null) {
            abort(404);
        }

        $model = static::getRecordRouteBindingEloquentQuery($resource)->findOrFail((string) $record);

        static::authorizeEdit($resource, $model);

        $data = [];

        foreach ($schema->getComponentsRecursively() as $component) {
            $name = $component->getName();

            if ($name === null) {
                continue;
            }

            $data[$name] = $component instanceof TextInput && $component->isPassword()
                ? ''
                : $model->getAttribute($name);
        }

        // Merge order is deliberate: the page-level props (resource,
        // resourceTitle, view data) spread last so they are authoritative
        // over any schema payload key of the same name. The relations list
        // drives the relation-manager tabs under the edit form (slice 1.8):
        // one entry per manager the resource registers, keyed by the
        // relationship each hosts and labelled for the tab.
        $relations = [];

        foreach ($refilament->getRelationManagers($resource) as $relationship => $manager) {
            $relations[] = [
                'name' => $relationship,
                'label' => $manager::getTitle(),
            ];
        }

        return [
            // Serialized for the edit operation so `hiddenOn('edit')` /
            // `disabledOn('edit')` fields render accordingly (slice C6).
            ...$schema->toArray('edit'),
            'data' => $data,
            'errors' => [],
            'record' => $model->getKey(),
            'relations' => $relations,
            ...parent::getPayload($resource, $refilament),
        ];
    }
}
