<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Refilament\Refilament\Http\Concerns\ValidatesSchemaData;
use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Action;
use Refilament\Refilament\Tables\Table;

/**
 * Serve one resource's relation-manager table scoped to the records of one
 * owner (slice 1.8; docs/CONTRACT.md, "Relations").
 *
 * Mirrors the table index endpoint (docs/CONTRACT.md, "Tables") one-to-one:
 * the same query-param validation and the same pagination/sort/search/filter
 * over an Eloquent builder, then the same table payload. The single difference
 * is provenance — the query is the owner's to-many relationship, rebuilt from
 * the parent on every request (no server-side component state), so only that
 * owner's related records are ever served.
 */
class RelationManagerController
{
    use ValidatesSchemaData;

    /**
     * GET /refilament/relation/{resource}/{record}/{relation}
     *
     * {resource} is a resource table id, {record} a primary key of that
     * resource's model (the owner), {relation} a to-many relationship name on
     * the owner referenced by one of the resource's relation managers.
     *
     * Query params mirror the table index endpoint: page, perPage, sort,
     * direction, search and filter[<name>].
     */
    public function index(Request $request, Refilament $refilament, string $resource, string $record, string $relation): JsonResponse
    {
        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', 'string'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'search' => ['sometimes', 'string'],
            'filter' => ['sometimes', 'array'],
        ]);

        $resourceClass = $refilament->getResourceClass($resource);

        if ($resourceClass === null) {
            return response()->json(['error' => 'Unknown resource.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $relationClass = $refilament->getRelationManager($resource, $relation);

        if ($relationClass === null) {
            return response()->json(['error' => 'Unknown relation.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $owner = ($resourceClass::getModel())::find($record);

        if ($owner === null) {
            return response()->json(['error' => 'Owner record not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Build the scope from the owner's named relationship. Only to-many
        // relationships produce a list; a singular one (belongsTo, hasOne)
        // has no rows to page, so refuse it explicitly rather than misbehave.
        $relation = $owner->{$relationClass::getRelationshipName()}();

        if (! $relation instanceof HasMany && ! $relation instanceof BelongsToMany) {
            return response()->json(['error' => 'Relation is not a to-many relationship.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // The manager's table() supplies columns/filters/sort; the scoped
        // relationship is the authoritative query, so pagination/sorting and
        // filters always stay within the owner's records. The relation's
        // getQuery() carries the owner-fk constraint the builder applies.
        $resolved = $relationClass::table(Table::make())->query($relation->getQuery());

        $page = $request->integer('page', 1);
        $perPage = $request->query('perPage') !== null
            ? $request->integer('perPage')
            : $resolved->getRecordsPerPage();

        if (! in_array($perPage, $resolved->getRecordsPerPageSelectOptions(), true)) {
            $perPage = $resolved->getRecordsPerPage();
        }

        $sort = $request->query('sort');

        if ($sort !== null && ! $this->isSortableColumn($resolved, $sort)) {
            return response()->json(['error' => 'Column is not sortable.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $direction = (string) $request->query('direction', 'asc');

        $search = $request->query('search');

        if ($search !== null && $resolved->getSearchableColumns() === []) {
            return response()->json(['error' => 'Table has no searchable columns.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $filters = $this->resolveFilters($request, $resolved);

        if ($filters === null) {
            return response()->json(['error' => 'Unknown filter.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = $resolved->toPayload($page, $perPage, $sort, $direction, $search, $filters);

        // Identify the relation for the client, mirroring the `id` the table
        // index endpoint adds per table.
        $payload['id'] = $relationClass::getRelationshipName();

        return response()->json($payload);
    }

    /**
     * GET /refilament/relation/{resource}/{record}/{relation}/record/{item}
     *
     * Serve the values of one related record pre-filled into its form (docs/CONTRACT.md,
     * "Relations"). The modal edit (slice 1.8) fetches the form document and these
     * values together, submitting through the manager's action endpoint. Mirrors
     * TableController::record one-to-one, with the record resolved through the owner's
     * scoped query so a pre-fill can never read another owner's record.
     *
     * Query params: schema (the form schema document id).
     */
    public function record(Request $request, Refilament $refilament, string $resource, string $record, string $relation, string $item): JsonResponse
    {
        $request->validate([
            'schema' => ['required', 'string'],
        ]);

        $resourceClass = $refilament->getResourceClass($resource);

        if ($resourceClass === null) {
            return response()->json(['error' => 'Unknown resource.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $relationClass = $refilament->getRelationManager($resource, $relation);

        if ($relationClass === null) {
            return response()->json(['error' => 'Unknown relation.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $schema = $refilament->resolveSchema((string) $request->query('schema'));

        if ($schema === null) {
            return response()->json(['error' => 'Unknown schema.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $owner = ($resourceClass::getModel())::find($record);

        if ($owner === null) {
            return response()->json(['error' => 'Owner record not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $relationInstance = $owner->{$relationClass::getRelationshipName()}();

        if (! $relationInstance instanceof HasMany && ! $relationInstance instanceof BelongsToMany) {
            return response()->json(['error' => 'Relation is not a to-many relationship.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $resolved = $relationClass::table(Table::make())->query($relationInstance->getQuery());

        $model = $resolved->findRecord($item);

        if ($model === null) {
            return response()->json(['error' => 'Record not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $this->serializeRecordData($schema, $model)]);
    }

    /**
     * POST /refilament/relation/{resource}/{record}/{relation}/action/{action}
     *
     * Run one of the manager's table actions scoped to the owner (docs/CONTRACT.md,
     * "Relations"). Mirrors TableController::action (docs/CONTRACT.md, "Tables"),
     * with two request shapes:
     *
     *  - Header **create** action — body `{ "data": {...} }`. Validated against
     *    the manager's form schema and created **as a record of this owner** (the
     *    relationship sets the foreign key), so a create never end up orphaned or
     *    attached to the wrong parent.
     *  - Row action (delete, or modal `edit`)/body `{ "record": <key>, "data": {...} }`.
     *    The record is resolved **through the owner's scoped query**, so an action
     *    can never touch another owner's record; `data` is validated against the
     *    manager's form schema (uniqueness rules ignore the record) before the
     *    action closure runs with `($record, $data)`.
     *
     * `404` for unknown resource/relation/action, a missing owner or a record the
     * owner cannot see; `422` for invalid data or an action no longer visible.
     */
    public function action(Request $request, Refilament $refilament, string $resource, string $record, string $relation, string $action): JsonResponse
    {
        $request->validate([
            'record' => ['sometimes'],
            'data' => ['sometimes', 'array'],
        ]);

        $resourceClass = $refilament->getResourceClass($resource);

        if ($resourceClass === null) {
            return response()->json(['error' => 'Unknown resource.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $relationClass = $refilament->getRelationManager($resource, $relation);

        if ($relationClass === null) {
            return response()->json(['error' => 'Unknown relation.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $owner = ($resourceClass::getModel())::find($record);

        if ($owner === null) {
            return response()->json(['error' => 'Owner record not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $relationInstance = $owner->{$relationClass::getRelationshipName()}();

        if (! $relationInstance instanceof HasMany && ! $relationInstance instanceof BelongsToMany) {
            return response()->json(['error' => 'Relation is not a to-many relationship.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // The manager's table() supplies the actions (row + header create); the
        // owner-scoped relationship is the authoritative query so row actions
        // resolve the record through exactly the records this owner can see.
        $resolved = $relationClass::table(Table::make())->query($relationInstance->getQuery());

        $actionInstance = $this->findActionInstance($resolved, $action);

        if ($actionInstance === null) {
            return response()->json(['error' => 'Unknown action.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = $request->input('data', []);

        if (! is_array($data)) {
            return response()->json(['error' => 'The data must be an array.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Header 'create': no record — build a new related record owned by this
        // owner from the manager's form schema. Only hasMany-style relationships
        // know how to set the parent's foreign key on create; belongsToMany and
        // friends create/attach in a pivot-aware way that is out of scope here.
        if ($actionInstance->getType() === 'create') {
            if (! $relationInstance instanceof HasMany) {
                return response()->json(['error' => 'Relation does not support create.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $schema = $relationClass::form(Schema::make());
            $validated = $this->validateSchemaData($schema, $data);

            // HasMany::create() assigns the parent's foreign key on the new
            // record, so the created comment always belongs to this owner.
            $relationInstance->create($validated);

            $message = $actionInstance->getSuccessMessage();

            return response()->json([
                'success' => true,
                ...Notification::toResponseArray($actionInstance->getSuccessNotification(), $message),
            ]);
        }

        // Row action (edit / delete): requires the record.
        if (! $request->filled('record')) {
            return response()->json(['error' => 'The record is required.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $target = $resolved->findRecord((string) $request->input('record'));

        if ($target === null) {
            return response()->json(['error' => 'Record not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Visibility is server-authoritative — never run an action the table
        // would not offer for this record now.
        if (! $actionInstance->isVisibleFor($target)) {
            return response()->json(['error' => 'Action is not available for this record.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // An action linking a form schema (modal edit) validates its data against
        // the manager's form — the same authoritative rules the create path uses —
        // with uniqueness rules ignoring the record being edited.
        if ($actionInstance->getSchema() !== null) {
            $schema = $relationClass::form(Schema::make());
            $data = $this->validateSchemaData($schema, $data, (string) $target->getKey());
        }

        try {
            $actionInstance->call($target, $data);
        } catch (LogicException $logicException) {
            throw $logicException;
        } catch (Exception $exception) {
            throw ValidationException::withMessages([
                'action' => $exception->getMessage(),
            ]);
        }

        $message = $actionInstance->getSuccessMessage();

        return response()->json([
            'success' => true,
            ...Notification::toResponseArray($actionInstance->getSuccessNotification(), $message),
        ]);
    }

    /**
     * Resolve an action by name across a relation manager's row *and* header
     * actions — header create actions live outside the row list.
     */
    private function findActionInstance(Table $resolved, string $name): ?Action
    {
        $action = $resolved->findAction($name);

        if ($action !== null) {
            return $action;
        }

        foreach ($resolved->getHeaderActions() as $headerAction) {
            if ($headerAction->getName() === $name) {
                return $headerAction;
            }
        }

        return null;
    }

    private function isSortableColumn(Table $resolved, string $sort): bool
    {
        foreach ($resolved->getColumns() as $column) {
            if ($column->getName() === $sort && $column->isSortable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string|array<int, string>>|null null when a filter
     *                                                       references an unknown name
     */
    private function resolveFilters(Request $request, Table $resolved): ?array
    {
        $submitted = $request->query('filter');

        if (! is_array($submitted) || $submitted === []) {
            return [];
        }

        $known = [];

        foreach ($resolved->getFilters() as $filter) {
            $known[(string) $filter->getName()] = $filter;
        }

        $filters = [];

        foreach ($submitted as $name => $value) {
            if (! isset($known[$name])) {
                return null;
            }

            $filters[$name] = is_array($value) ? array_map('strval', $value) : (string) $value;
        }

        return $filters;
    }
}
