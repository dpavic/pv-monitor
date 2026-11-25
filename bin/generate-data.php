<?php

declare(strict_types=1);

// Fills the database with simulated measurements for the last N days up to now.
// Usage: php bin/generate-data.php --days=365
// Existing measurements are deleted first.

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Simulator.php';

$options = getopt('', ['days:']);
$days    = filter_var($options['days'] ?? 365, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 3650],
]);

if ($days === false) {
    fwrite(STDERR, "Invalid --days value (1–3650).\n");
    exit(1);
}

$pdo        = Database::connection();
$repository = new MeasurementRepository($pdo);
$simulator  = new Simulator(50.0);

$end   = Simulator::floorToInterval(new DateTimeImmutable('now'));
$start = $end->modify("-$days days")->setTime(0, 0);

echo "Generating data from {$start->format('Y-m-d')} to {$end->format('Y-m-d H:i')} ...\n";

$repository->deleteAll();

// One transaction for all rows is much faster than committing each insert
$pdo->beginTransaction();
$count = 0;

// measurementsBetween() excludes the start, so begin one interval earlier
$from = $start->modify('-' . Simulator::INTERVAL_MINUTES . ' minutes');

foreach ($simulator->measurementsBetween($from, $end) as $measurement) {
    $repository->insert($measurement);
    $count++;
}

$pdo->commit();

// Short summary as a plausibility check (power in W * 0.25 h = Wh)
$totals = $pdo->query(
    'SELECT SUM(pv_power_w) * 0.25 / 1000                    AS pv_kwh,
            SUM(load_power_w) * 0.25 / 1000                  AS load_kwh,
            SUM(GREATEST(grid_power_w, 0)) * 0.25 / 1000     AS import_kwh,
            SUM(GREATEST(-grid_power_w, 0)) * 0.25 / 1000    AS export_kwh,
            SUM(pv_power_w + grid_power_w <> load_power_w + battery_power_w) AS balance_errors
     FROM measurements'
)->fetch();

printf("Inserted %d measurements.\n", $count);
printf("PV production:  %8.0f kWh\n", $totals['pv_kwh']);
printf("Consumption:    %8.0f kWh\n", $totals['load_kwh']);
printf("Grid import:    %8.0f kWh\n", $totals['import_kwh']);
printf("Grid export:    %8.0f kWh\n", $totals['export_kwh']);
printf("Balance errors: %8d\n", $totals['balance_errors']);
