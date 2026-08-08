<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Refilament\Refilament\Refilament;

class SchemaValidationController
{
    /**
     * Live-validate a single field's unique/availability rule (slice 2.5 —
     * docs/CONTRACT.md, "Live validation"). The "is this slug/email already
     * taken" check is a deliberate, debounced server round-trip from the
     * client — never a hidden Livewire closure re-execution. The rules stay
     * server-authoritative: the client calls back into the schema's own
     * rules, and only the field's `unique` rule is evaluated (other rules
     * remain submit-time concerns).
     *
     * Body:     { "field": "slug", "value": "my-post", "data": { ... }, "record": 1? }
     * Valid:    200 { "valid": true }
     * Taken:    200 { "valid": false, "errors": { "slug": ["The Slug has already been taken."] } }
     * Unknown:  404 { "error": "Unknown schema." } / "Unknown field [...]."
     */
    public function validate(Request $request, Refilament $refilament, string $schema): JsonResponse
    {
        $request->validate([
            'field' => ['required', 'string'],
            'value' => ['present'],
            'data' => ['sometimes', 'array'],
            'record' => ['sometimes', 'string', 'numeric'],
        ]);

        $resolved = $refilament->resolveSchema($schema);

        if ($resolved === null) {
            return response()->json(['error' => 'Unknown schema.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $fieldName = $request->string('field')->toString();

        $field = $resolved->getComponentByName($fieldName);

        if ($field === null) {
            return response()->json(['error' => "Unknown field [{$fieldName}]."], JsonResponse::HTTP_NOT_FOUND);
        }

        $rules = array_values(array_filter(
            $field->getValidationRules(),
            static fn (string $rule): bool => preg_match('/^unique:/', $rule) === 1,
        ));

        if ($rules === []) {
            return response()->json([
                'error' => "Field [{$fieldName}] has no unique rule to validate live.",
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Editing a record: the unique rule ignores the record being edited,
        // exactly like the typed record update endpoint.
        $rules = $resolved->ignoreCurrentRecordInUniqueRules([$fieldName => $rules], (string) $request->string('record', ''));

        $validator = Validator::make(
            [$fieldName => $request->input('value')],
            $rules,
        );
        $validator->setAttributeNames([$fieldName => $field->getLabel()]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
            ]);
        }

        return response()->json(['valid' => true]);
    }
}
