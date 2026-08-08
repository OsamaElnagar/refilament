<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Refilament\Refilament\Http\Concerns\ValidatesSchemaData;
use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Tables\Table;

class TableController
{
    use ValidatesSchemaData;

    /**
     * Serve one page of a registered table's rows as JSON (docs/CONTRACT.md,
     * "Tables"). Pagination and sorting are server-side: the React runtime
     * fetches this endpoint on every page or sort change instead of doing an
     * Inertia visit.
     *
     * Query params: page (default 1), perPage (defaults to the table's
     * recordsPerPage; any value outside the table's allowed options is
     * clamped back to the default), sort (a sortable column name),
     * direction ('asc' | 'desc', default 'asc'), search (a global term
     * matching searchable columns), filter[<name>] (a filter value or
     * repeated values for multiple filters) and group (a registered grouping
     * column — slice 2.3).
     */
    public function index(Request $request, Refilament $refilament, string $table): JsonResponse
    {
        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', 'string'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'search' => ['sometimes', 'string'],
            'filter' => ['sometimes', 'array'],
            'group' => ['sometimes', 'string'],
        ]);

        $resolved = $refilament->resolveTable($table);

        if ($resolved === null) {
            return response()->json(['error' => 'Unknown table.'], JsonResponse::HTTP_NOT_FOUND);
        }

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

        // An unknown group column is a client/view bug, not a degraded view —
        // reject it so the payload never silently drops grouping.
        // Also emit the group the table would apply by default so the client
        // selector highlights it when no explicit `group` param was sent.
        $requestedGroup = $request->query('group');

        if ($requestedGroup !== null && ! array_key_exists((string) $requestedGroup, $resolved->getGroups())) {
            return response()->json(['error' => 'Group column is not registered.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $group = $resolved->resolveActiveGroup($requestedGroup !== null ? (string) $requestedGroup : null);

        $payload = $resolved->toPayload($page, $perPage, $sort, $direction, $search, $filters, $group);
        $payload['id'] = $table;

        if ($group !== null) {
            $payload['activeGroup'] = $group;
        }

        return response()->json($payload);
    }

    /**
     * Run a table row action against one record (docs/CONTRACT.md, "Tables").
     * The action closure runs server-side with the resolved record; no state
     * survives between requests.
     *
     * Body: { "record": <primary key> } — plus {"data": {...}} for actions
     * that link a form schema document (modal edit, slice 1.2). When data is
     * submitted, it is validated against the linked schema's authoritative
     * rules (422 with per-field errors on failure) before the closure runs.
     */
    public function action(Request $request, Refilament $refilament, string $table, string $action): JsonResponse
    {
        $request->validate([
            'record' => ['required'],
            'data' => ['sometimes', 'array'],
        ]);

        $resolved = $refilament->resolveTable($table);

        if ($resolved === null) {
            return response()->json(['error' => 'Unknown table.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $actionInstance = $resolved->findAction($action);

        if ($actionInstance === null) {
            return response()->json(['error' => 'Unknown action.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $record = $resolved->findRecord((string) $request->input('record'));

        if ($record === null) {
            return response()->json(['error' => 'Record not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = $request->input('data', []);

        if (! is_array($data)) {
            return response()->json(['error' => 'The data must be an array.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Visibility is server-authoritative: the row only rendered this
        // action when it was visible, but the record may have changed since.
        // Never run an action the table would not offer for this record now.
        if (! $actionInstance->isVisibleFor($record)) {
            return response()->json(['error' => 'Action is not available for this record.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Actions with a linked form schema (modal edit) validate their data
        // against that schema's rules — the same authoritative rules the
        // submit endpoint uses — before the closure runs.
        if ($actionInstance->getSchema() !== null) {
            $schema = $refilament->resolveSchema($actionInstance->getSchema());

            if ($schema === null) {
                throw new LogicException("Action [{$action}] links an unknown schema [{$actionInstance->getSchema()}] — a configuration bug.");
            }

            // Uniqueness rules ignore the record being edited — a record
            // never rejects its own values (Laravel's unique rule would
            // otherwise fail an unchanged slug against itself).
            $data = $this->validateSchemaData($schema, $data, (string) $record->getKey(), 'edit');
        }

        try {
            $actionInstance->call($record, $data);
        } catch (LogicException $logicException) {
            // Misconfigured action (e.g. missing closure) — a server bug, not
            // a user-facing failure. Let it surface as a 500.
            throw $logicException;
        } catch (Exception $exception) {
            // Domain failures inside the action closure reach the client as a
            // 422 with the message, mirroring form validation errors.
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
     * Run a table bulk (toolbar) action against a set of selected records
     * (slice 2.2; docs/CONTRACT.md, "Bulk actions"). Selection lives client-side,
     * so the client sends the concrete record keys it selected — the server
     * never remembers a selection between requests. The action closure runs
     * once against the resolved Eloquent Collection of records.
     *
     * Body: { "records": [<primary key>, ...] }
     */
    public function bulk(Request $request, Refilament $refilament, string $table, string $action): JsonResponse
    {
        $request->validate([
            'records' => ['required', 'array', 'min:1'],
            'records.*' => ['required'],
        ]);

        $resolved = $refilament->resolveTable($table);

        if ($resolved === null) {
            return response()->json(['error' => 'Unknown table.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $actionInstance = $resolved->findBulkAction($action);

        if ($actionInstance === null) {
            return response()->json(['error' => 'Unknown action.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // General authorization gate (slice 4.1): a bulk action the current
        // user may not run is refused even when a client still sends it. The
        // table serialization already omits unauthorized bulk actions, so this
        // is the authoritative server-side re-check.
        if (! $actionInstance->isAuthorized()) {
            return response()->json(['error' => 'Action is not available.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $keys = array_map('strval', $request->input('records'));
        $records = $resolved->findRecords($keys);

        // Records selected on the client may have been deleted since. The bulk
        // action should only run against records the table still sees.
        if (count($records) !== count($keys)) {
            return response()->json(['error' => 'Some selected records were not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Per-record authorization (slice 4.1): when the bulk action declares
        // authorizeIndividualRecords(), records the current user cannot act on
        // are filtered out before the closure runs.
        $records = $actionInstance->filterRecords($records);

        try {
            $actionInstance->call($records);
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
     * Update one record through its form schema (slice 1.7 — the typed
     * update endpoint; docs/CONTRACT.md, "Record pages"). The full-page
     * edit form submits here instead of through the table's edit action: the
     * data is validated against the resource's form rules (the unique rule
     * ignores the record being edited, exactly like the modal action path),
     * persisted via the schema's updateUsing() handler (defaulting to a
     * mass-assignment update), and the record's fresh values are returned.
     *
     * Body: { "data": { "title": "...", ... } }
     * OK:    200 { "success": true, "message": "Post updated.", "data": { ... } }
     * Fails: 422 { "errors": { "title": ["..."] } }
     */
    public function update(Request $request, Refilament $refilament, string $table, string $record): JsonResponse
    {
        $request->validate([
            'data' => ['sometimes', 'array'],
        ]);

        $resolved = $refilament->resolveTable($table);

        if ($resolved === null) {
            return response()->json(['error' => 'Unknown table.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = $request->input('data');

        if (! is_array($data)) {
            return response()->json(['error' => 'The data must be an array.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $model = $resolved->findRecord($record);

        if ($model === null) {
            return response()->json(['error' => 'Record not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // The resource's form schema is the authoritative rules source — the
        // same schema document the edit page payload serializes. Tables
        // registered outside a resource have no form and no update path.
        $class = $refilament->getResourceClass($table);

        if ($class === null) {
            return response()->json(['error' => 'Table has no form schema.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $schema = $refilament->resolveSchema($class::getFormId());

        if ($schema === null) {
            throw new LogicException("Table [{$table}] has no registered form schema [{$class::getFormId()}] — a configuration bug.");
        }

        // Uniqueness rules ignore the record being edited — a record never
        // rejects its own values (Laravel's unique rule would otherwise fail
        // an unchanged slug against itself).
        $validated = $this->validateSchemaData($schema, $data, (string) $model->getKey(), 'edit');

        try {
            $schema->update($model, $validated);
        } catch (LogicException $logicException) {
            throw $logicException;
        } catch (Exception $exception) {
            throw ValidationException::withMessages([
                'form' => $exception->getMessage(),
            ]);
        }

        $message = $schema->getUpdateSuccessMessage();

        return response()->json([
            'success' => true,
            ...Notification::toResponseArray($schema->getUpdateSuccessNotification(), $message),
            // Fresh values after the update, serialized like the pre-fill
            // endpoint — password fields always blank, secrets never leave.
            'data' => $this->serializeRecordData($schema, $model),
        ]);
    }

    /**
     * Serve the values of one record pre-filled into its form document
     * (docs/CONTRACT.md, "Modal actions" — slice 1.2). The client passes
     * the form schema id so only the fields the form knows are returned — a
     * record's other attributes (and secrets) never leave the server.
     * Password-typed fields pre-fill empty: the stored hash is never
     * serialized back to the client.
     *
     * Query params: schema (the form schema document id).
     */
    public function record(Request $request, Refilament $refilament, string $table, string $record): JsonResponse
    {
        $request->validate([
            'schema' => ['required', 'string'],
        ]);

        $resolved = $refilament->resolveTable($table);

        if ($resolved === null) {
            return response()->json(['error' => 'Unknown table.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $schema = $refilament->resolveSchema((string) $request->query('schema'));

        if ($schema === null) {
            return response()->json(['error' => 'Unknown schema.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $model = $resolved->findRecord($record);

        if ($model === null) {
            return response()->json(['error' => 'Record not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $this->serializeRecordData($schema, $model)]);
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
