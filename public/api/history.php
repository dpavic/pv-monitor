<?php

declare(strict_types=1);

// GET /api/history.php?range=month|year&date=YYYY-MM-DD
//   range=month: one entry per day of the month that contains "date"
//   range=year:  one entry per month of the year that contains "date"
// Periods without data are included with null values, so the chart always
// shows the complete month / year.

require_once __DIR__ . '/../../src/bootstrap.php';

require_get_request();

$range = $_GET['range'] ?? 'month';
$date  = parse_date($_GET['date'] ?? date('Y-m-d'));

if (!in_array($range, ['month', 'year'], true)) {
    json_response(['error' => 'Ungültiger Zeitraum. Erlaubt: month, year.'], 400);
}

if ($date === null) {
    json_response(['error' => 'Ungültiges Datum. Erwartet wird YYYY-MM-DD.'], 400);
}

if ($range === 'month') {
    $start     = $date->modify('first day of this month');
    $end       = $start->modify('+1 month');
    $step      = '+1 day';
    $keyFormat = 'Y-m-d';
} else {
    $start     = $date->setDate((int) $date->format('Y'), 1, 1);
    $end       = $start->modify('+1 year');
    $step      = '+1 month';
    $keyFormat = 'Y-m';
}

try {
    $repository = new MeasurementRepository(Database::connection());
    $rows = $range === 'month'
        ? $repository->dailyTotals($start, $end)
        : $repository->monthlyTotals($start, $end);
} catch (Throwable $e) {
    error_log('api/history.php: ' . $e->getMessage());
    json_response(['error' => 'Interner Fehler.'], 500);
}

$empty = ['pv_kwh' => null, 'load_kwh' => null, 'grid_import_kwh' => null, 'grid_export_kwh' => null];

// One entry for every day / month, with null where nothing was measured
$periods = [];
for ($current = $start; $current < $end; $current = $current->modify($step)) {
    $key       = $current->format($keyFormat);
    $periods[] = ['period' => $key] + ($rows[$key] ?? $empty);
}

// Totals of the whole month / year
$totals = [];
foreach (array_keys($empty) as $column) {
    $totals[$column] = round(array_sum(array_column($rows, $column)), 2);
}

json_response([
    'range'   => $range,
    'start'   => $start->format('Y-m-d'),
    'summary' => with_ratios($totals),
    'periods' => $periods,
]);
