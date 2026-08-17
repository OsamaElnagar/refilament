<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Refilament;

/**
 * Run a global-search result action against one record (slice 3.5;
 * docs/CONTRACT.md, "Global search"). Mirrors the table action endpoint — the
 * honest request/response model: the React runtime POSTs the action name, the
 * resource, and the record's primary key; the server rebuilds the action from
 * the resource, re-checks visibility and the per-record authorization gate,
 * then calls the closure. No component state survives between requests.
 *
 * Body: { "record": <primary key> }
 * OK:   200 { "success": true, "message": "..." } (plus a richer notification
 *       when one is set).
 */
class GlobalSearchActionController
{
    public function __invoke(Request $request, Refilament $refilament, string $resource, string $action): JsonResponse
    {
        $request->validate([
            'record' => ['required'],
        ]);

        $class = $refilament->getResourceClass($resource);

        if ($class === null) {
            return response()->json(['error' => 'Unknown resource.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // An action endpoint is only reachable when the resource can appear in
        // global search at all — a resource the current user can't access
        // offers no server actions.
        if (! $class::canGloballySearch()) {
            return response()->json(['error' => 'Resource cannot be globally searched.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $record = $class::getGlobalSearchEloquentQuery()
            ->whereKey((string) $request->input('record'))
            ->first();

        if ($record === null) {
            return response()->json(['error' => 'Record not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // The record must be reachable by the current user — the same gate the
        // search uses to decide whether to show it (docs/CONTRACT.md,
        // "Global search"). Never run an action on a record the user can't see.
        if (! $class::canGlobalSearchRecordBeShown($record)) {
            return response()->json(['error' => 'Record is not visible.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $actionInstance = collect($class::getGlobalSearchResultActions($record))
            ->first(fn (Action $candidate): bool => $candidate->getName() === $action);

        if (! $actionInstance instanceof Action) {
            return response()->json(['error' => 'Unknown action.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (! $actionInstance->isVisibleFor($record)) {
            return response()->json(['error' => 'Action is not available for this record.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $actionInstance->call($record);
        } catch (LogicException $logicException) {
            // Misconfigured action (e.g. missing closure) — a server bug.
            throw $logicException;
        } catch (Exception $exception) {
            // Domain failures inside the closure reach the client as a 422,
            // mirroring form validation errors.
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
}
