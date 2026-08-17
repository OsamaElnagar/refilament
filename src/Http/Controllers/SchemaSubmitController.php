<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;
use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Refilament;

class SchemaSubmitController
{
    /**
     * Validate and submit a schema document's form data (docs/CONTRACT.md,
     * \"Form submission\"). Rules are server-authoritative — the client's
     * serialized copy is a hint only — and errors map back onto the fields
     * by name, never an Inertia visit.
     *
     * Body:  { \"data\": { \"title\": \"...\", \"status\": \"draft\" } }
     * OK:    200 { \"success\": true, \"message\": \"Post created.\" }
     * Fails: 422 { \"errors\": { \"title\": [\"The Title field is required.\"] } }
     */
    public function submit(Request $request, Refilament $refilament, string $schema): JsonResponse
    {
        // `sometimes` (not `required`): an empty object is a legitimate form
        // payload — Laravel's `required` rule rejects empty arrays. Missing
        // data is caught by the array guard below.
        $request->validate([
            'data' => ['sometimes', 'array'],
        ]);

        $resolved = $refilament->resolveSchema($schema);

        if ($resolved === null) {
            return response()->json(['error' => 'Unknown schema.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = $request->input('data');

        if (! is_array($data)) {
            return response()->json(['error' => 'The data must be an array.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Operation-aware validation (slice C6): a `hiddenOn('create')` field
        // must not fail create validation with an invisible "required" error.
        // The client sends its operation (create vs edit) as a query param.
        $operation = $request->query('operation');

        // Conditional rules evaluate against the submitted values via a Get
        // injection — a per-request snapshot (docs/ARCHITECTURE.md).
        $resolved->setValidationData($data);

        $validator = Validator::make($data, $resolved->getValidationRules(is_string($operation) ? $operation : null));
        $validator->setAttributeNames($resolved->getValidationAttributes());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->toArray()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $resolved->submit($validator->validated());
        } catch (LogicException $logicException) {
            // Misconfigured schema (e.g. missing submit handler) — a server
            // bug, not a user-facing failure. Let it surface as a 500.
            throw $logicException;
        } catch (Exception $exception) {
            // Domain failures inside the submit handler reach the client as a
            // 422 with the message, mirroring table action failures.
            throw ValidationException::withMessages([
                'form' => $exception->getMessage(),
            ]);
        }

        // A model-bound schema that created through its default path (no
        // submitUsing()/successMessage() — slice 2.6) still gets a sensible
        // toast, mirroring the update endpoint's default message.
        $message = $resolved->getSuccessMessage() ?? ($resolved->getModel() !== null ? 'Created successfully.' : null);

        return response()->json([
            'success' => true,
            ...Notification::toResponseArray($resolved->getSuccessNotification(), $message),
        ]);
    }
}
