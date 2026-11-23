<?php

declare(strict_types=1);

// GET /api/day.php?date=YYYY-MM-DD   (without date: today)
// Returns all measurements of one day plus the daily totals and ratios.

require_once __DIR__ . '/../../src/bootstrap.php';

require_get_request();

$day = parse_date($_GET['date'] ?? date('Y-m-d'));

if ($day === null) {
    json_response(['error' => 'Ungültiges Datum. Erwartet wird YYYY-MM-DD.'], 400);
}

try {
    $repository   = new MeasurementRepository(Database::connection());
    $measurements = $repository->findByDay($day);
    $totals       = $repository->daySummary($day);
} catch (Throwable $e) {
    error_log('api/day.php: ' . $e->getMessage());
    json_response(['error' => 'Interner Fehler.'], 500);
}

/**
 * Part / total in percent, or null if the total is 0 (e.g. no sun at all).
 */
function percent(float $part, float $total): ?float
{
    return $total > 0 ? round($part / $total * 100, 1) : null;
}

// Self-consumption rate: share of the PV production used in the house (incl. battery)
$selfConsumption = percent($totals['pv_kwh'] - $totals['grid_export_kwh'], $totals['pv_kwh']);

// Self-sufficiency rate: share of the consumption NOT bought from the grid
$selfSufficiency = percent($totals['load_kwh'] - $totals['grid_import_kwh'], $totals['load_kwh']);

json_response([
    'date'         => $day->format('Y-m-d'),
    'summary'      => $totals + [
        'self_consumption_percent' => $selfConsumption,
        'self_sufficiency_percent' => $selfSufficiency,
    ],
    'measurements' => $measurements,
]);
