<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Workbench\App\Support\PlaygroundSchema;

final class PlaygroundController
{
    /**
     * Serve the schema playground — an app-owned standalone page (the
     * slice 1.6 page system's base Page), wired by hand here because the
     * panel shell that registers standalone pages lands with 1.9.
     */
    public function __invoke(): InertiaResponse
    {
        $schema = PlaygroundSchema::make();

        return Inertia::render('refilament/playground', [
            ...$schema->toArray(),
            'data' => PlaygroundSchema::data(),
            'errors' => [],
        ]);
    }
}
