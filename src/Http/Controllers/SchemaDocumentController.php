<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Refilament\Refilament\Refilament;

class SchemaDocumentController
{
    /**
     * Serve a schema document as JSON (docs/CONTRACT.md, "Modal actions").
     *
     * Modal actions (slice 1.1) fetch the resource's form document here
     * instead of receiving it through an Inertia page render. The document
     * is identical to what the auto-registered create page serves — the same
     * nodes, and the same initial data derived from the fields' defaults —
     * so a modal form and a full-page form always present the same values.
     */
    public function show(Refilament $refilament, string $schema): JsonResponse
    {
        $resolved = $refilament->resolveSchema($schema);

        if ($resolved === null) {
            return response()->json(['error' => 'Unknown schema.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return response()->json([
            ...$resolved->toArray(),
            // The single derivation point for form defaults — the same
            // Schema::initialData() Resource::formData() delegates to, so the
            // modal never disagrees with the full-page create form.
            'data' => $resolved->initialData(),
            'errors' => [],
        ]);
    }
}
