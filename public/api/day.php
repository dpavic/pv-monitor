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

json_response([
    'date'         => $day->format('Y-m-d'),
    'summary'      => with_ratios($totals),
    'measurements' => $measurements,
]);
