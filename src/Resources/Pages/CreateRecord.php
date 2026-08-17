<?php

declare(strict_types=1);

namespace Refilament\Refilament\Resources\Pages;

use Refilament\Refilament\Refilament;

/**
 * The built-in create page for a resource (slice 1.6) — the default 'create'
 * slot in Resource::getPages(). Renders the generic refilament/resource-create
 * Inertia page with the schema document, the resource's form defaults, and
 * the resource + resourceTitle props (the list route visited on success and
 * the model-derived heading).
 */
class CreateRecord extends Page
{
    /**
     * The page's own crumb in breadcrumbs (slice 1.11) — "Create", matching
     * Filament's create-record breadcrumb default.
     */
    protected static ?string $breadcrumb = 'Create';

    public static function getInertiaComponent(): string
    {
        return 'refilament/resource-create';
    }

    public static function getPayload(string $resource, Refilament $refilament, ?string $record = null): array
    {
        static::authorizeCreate($resource);

        $class = $refilament->getResourceClass($resource);

        if ($class === null) {
            abort(404);
        }

        $schema = $refilament->resolveSchema($class::getFormId());

        if ($schema === null) {
            abort(404);
        }

        // Merge order is deliberate: the page-level props (resource,
        // resourceTitle, view data) spread last so they are authoritative
        // over any schema payload key of the same name.
        // Serialized for the create operation so `hiddenOn('create')` /
        // `disabledOn('create')` fields render accordingly (slice C6).
        return [
            ...$schema->toArray('create'),
            'data' => $class::formData(),
            'errors' => [],
            ...parent::getPayload($resource, $refilament),
        ];
    }
}
