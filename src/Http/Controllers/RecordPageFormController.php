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
use Refilament\Refilament\Resources\Pages\Page;

/**
 * Submit the form of a record-scoped custom page against its record (the
 * record-pages slice — `/{record}/manage` hosting a `form()`). The record
 * lives in the URL, so the save always targets the exact record the page
 * manages: the page is resolved from the resource's getPages() map by its
 * slot name (mirroring the record-scoped page-action endpoint), the record
 * through the page's own record-binding query, the current user is gated by
 * the resource's edit policy, and the page's form schema validates + updates
 * with its unique rules ignoring the record being edited (so a manage page
 * never rejects a record's own values). The client addresses this endpoint
 * through the `submitUrl` the page payload serializes.
 *
 * Body:  { "data": { "name": "...", "price": 12.5 } }
 * OK:    200 { "success": true, "message": "...", "data": { ... } }
 * Fails: 422 { "errors": { "name": ["..."] } }
 */
class RecordPageFormController
{
    use ValidatesSchemaData;

    public function __invoke(Request $request, string $resource, string $page, string $record): JsonResponse
    {
        $request->validate([
            'data' => ['sometimes', 'array'],
        ]);

        $refilament = app(Refilament::class);

        $class = $refilament->getResourceClass($resource);

        if ($class === null) {
            return response()->json(['error' => 'Unknown resource.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $registration = $class::getPages()[$page] ?? null;

        if ($registration === null) {
            return response()->json(['error' => 'Unknown page.'], JsonResponse::HTTP_NOT_FOUND);
        }

        /** @var class-string<Page> $pageClass */
        $pageClass = $registration->getPage();

        $data = $request->input('data');

        if (! is_array($data)) {
            return response()->json(['error' => 'The data must be an array.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // The record this page manages — resolved through the page's own
        // record-binding query (soft-delete-aware overrides honored) and
        // gated by the resource's edit policy. Missing record: 404; the
        // current user may not edit it: 403.
        $model = $pageClass::getRecordRouteBindingEloquentQuery($resource)->findOrFail($record);

        $pageClass::authorizeRecord($resource, $model, 'edit');

        $schema = $pageClass::getFormSchema();

        if ($schema === null) {
            return response()->json(['error' => 'Page has no form.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Uniqueness rules ignore the record being edited — a record never
        // rejects its own values (Laravel's unique rule would otherwise fail
        // an unchanged slug against itself), the same rewrite the typed
        // record update endpoint applies.
        $validated = $this->validateSchemaData($schema, $data, (string) $model->getKey(), 'edit');

        try {
            $schema->update($model, $validated);
        } catch (LogicException $logicException) {
            // Misconfigured schema (e.g. no update path) — a server bug, not
            // a user-facing failure. Let it surface as a 500.
            throw $logicException;
        } catch (Exception $exception) {
            // Domain failures inside the update handler reach the client as a
            // 422 with the message, mirroring form validation errors.
            throw ValidationException::withMessages([
                'form' => $exception->getMessage(),
            ]);
        }

        // A record-scoped page form that declares no updateSuccessMessage()
        // still gets a sensible toast — the form stays on the page, so the
        // toast is its primary success feedback (mirroring the singular-
        // resource slice's default 'Saved.'). The consumer's message always
        // wins.
        $message = $schema->getUpdateSuccessMessage() ?? 'Saved.';

        return response()->json([
            'success' => true,
            ...Notification::toResponseArray($schema->getUpdateSuccessNotification(), $message),
            // Fresh values after the update, serialized like the record
            // pre-fill — password fields always blank, secrets never leave.
            'data' => $this->serializeRecordData($schema, $model),
        ]);
    }
}
