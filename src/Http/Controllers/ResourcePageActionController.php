<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Pages\Page;

/**
 * Run one record-scoped page header action (the Edit/Delete buttons on a
 * resource's edit and view pages) against the page's record — the server
 * half of the page-action slice. The page is resolved from the resource's
 * getPages() map by its slot name, and the action by name from that page's
 * getHeaderActions(), so the client never re-implements action resolution:
 * it POSTs the serialized `actionUrl` and the server re-checks visibility
 * (authorization + per-record visible closure) before invoking the closure.
 */
class ResourcePageActionController
{
    public function __invoke(Request $request, string $resource, string $page, string $record, string $action): JsonResponse
    {
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

        $model = $pageClass::getRecordRouteBindingEloquentQuery($resource)->findOrFail((string) $record);

        /** @var Action|null $found */
        $found = null;

        foreach ($pageClass::getHeaderActionInstances($resource) as $candidate) {
            if ($candidate->getName() === $action) {
                $found = $candidate;

                break;
            }
        }

        if ($found === null) {
            return response()->json(['error' => 'Unknown action.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Visibility is server-authoritative, mirroring the table action
        // endpoint: only run an action the page would offer for this record
        // right now.
        if (! $found->isVisibleFor($model)) {
            return response()->json(['error' => 'Action is not available for this record.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $found->call($model);
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

        return response()->json([
            'success' => true,
            ...($found->getSuccessMessage() !== null ? ['message' => $found->getSuccessMessage()] : []),
            ...($found->getSuccessNotification() !== null ? ['notification' => $found->getSuccessNotification()->toArray()] : []),
        ]);
    }
}
