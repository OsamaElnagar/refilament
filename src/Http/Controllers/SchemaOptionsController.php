<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Components\Select;

class SchemaOptionsController
{
    /**
     * Resolve fresh options for a field whose dependencies changed.
     *
     * This is the deliberate, visible server round-trip from
     * docs/CONTRACT.md — never a hidden closure re-execution. The client
     * addresses a schema document by its id (registered via
     * Refilament::registerSchemaResolver()) and the server evaluates the
     * field's `resolveOptionsUsing()` closure against the submitted data.
     *
     * Request body: { "schema": "playground", "field": "state", "data": { "country": "us" } }
     * Response:     { "options": [{ "value": "al", "label": "Alabama" }, ...] }
     */
    public function resolve(Request $request, Refilament $refilament): JsonResponse
    {
        $request->validate([
            'schema' => ['required', 'string'],
            'field' => ['required', 'string'],
            'data' => ['array'],
        ]);

        $schemaKey = $request->string('schema')->toString();
        $fieldName = $request->string('field')->toString();

        $data = $request->input('data', []);

        if (! is_array($data)) {
            return response()->json(['error' => 'The data must be an array.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $schema = $refilament->resolveSchema($schemaKey);

        if ($schema === null) {
            return response()->json(['error' => 'Unknown schema.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $field = $schema->getComponentByName($fieldName);

        if ($field === null) {
            return response()->json(['error' => "Unknown field [{$fieldName}]."], JsonResponse::HTTP_NOT_FOUND);
        }

        if (! $field instanceof Select || ! $field->hasOptionsResolver() || $field->getDependsOn() === null) {
            return response()->json([
                'error' => "Field [{$fieldName}] does not resolve dependent options.",
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'options' => $field->resolveOptions($data),
        ]);
    }
}
