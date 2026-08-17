<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Concerns;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;

/**
 * Shared server-side validation + record serialization for the typed form
 * endpoints (docs/CONTRACT.md, "Form submission" / "Record pages"). Both the
 * table endpoints and the relation-manager endpoints use the same authoritative
 * rules and the same record pre-fill behaviour, so password fields always
 * serialize blank and uniqueness rules always ignore the record being edited.
 */
trait ValidatesSchemaData
{
    /**
     * Build the validator that judges $data against a schema's authoritative
     * rules. When $ignoreRecordKey is given, `unique` rules are rewritten to
     * ignore that record so it never rejects its own values — the shared edit
     * behaviour across the modal action and typed update endpoints.
     *
     * @param  array<string, mixed>  $data
     */
    protected function schemaValidator(Schema $schema, array $data, ?string $ignoreRecordKey = null, ?string $operation = null): \Illuminate\Validation\Validator
    {
        // Closures in the rules (conditional required, rule providers) read
        // the submitted values through a Get injection — a stateless,
        // per-request snapshot, never a persisted component.
        $schema->setValidationData($data);

        $rules = $ignoreRecordKey !== null
            ? $schema->ignoreCurrentRecordInUniqueRules($schema->getValidationRules($operation), $ignoreRecordKey)
            : $schema->getValidationRules($operation);

        $validator = Validator::make($data, $rules);
        $validator->setAttributeNames($schema->getValidationAttributes());

        return $validator;
    }

    /**
     * Validate $data against a schema's authoritative rules and return the
     * validated values, throwing a 422 ValidationException (with per-field
     * errors) on failure. When $ignoreRecordKey is given, uniqueness rules
     * ignore that record (the edit path).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateSchemaData(Schema $schema, array $data, ?string $ignoreRecordKey = null, ?string $operation = null): array
    {
        $validator = $this->schemaValidator($schema, $data, $ignoreRecordKey, $operation);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    /**
     * One record's form-field values, keyed by field name — password-typed
     * fields always '' (the stored hash is never serialized back to the
     * client). Shared by the record pre-fill and update endpoints.
     *
     * @return array<string, mixed>
     */
    protected function serializeRecordData(Schema $schema, mixed $model): array
    {
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

        return $data;
    }
}
