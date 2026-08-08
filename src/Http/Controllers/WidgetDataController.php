<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Widgets\ChartWidget;

/**
 * Re-resolve a widget's data through the typed data endpoint (slice 3.2;
 * docs/CONTRACT.md, "Widgets"). The honest request/response model behind
 * chart polling and filters — the widget is rebuilt from its registered
 * resolver on every request (never any component state), the client-sent
 * `filter[...]` params reach the `data()` closure, and the response is a
 * fresh Chart.js-style snapshot.
 *
 * GET /refilament/widget/{widget}/data?filter[range]=30
 * OK:   200 { "data": { "labels": [...], "datasets": [...] } }
 */
class WidgetDataController
{
    public function __invoke(Request $request, Refilament $refilament, string $widget): JsonResponse
    {
        $instance = $refilament->resolveWidget($widget);

        if ($instance === null) {
            return response()->json(['error' => 'Unknown widget.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Only chart widgets expose live data today (the stats snapshot stays
        // a static render — no round trips for it).
        if (! $instance instanceof ChartWidget) {
            return response()->json(
                ['error' => 'Widget does not expose live data.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $filter = $request->query('filter');

        $filterData = is_array($filter) ? $filter : [];

        return response()->json([
            'data' => $instance->getData($filterData),
        ]);
    }
}
