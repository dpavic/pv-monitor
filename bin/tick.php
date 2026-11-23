<?php

declare(strict_types=1);

// Adds "live" measurements from the last stored row up to the current time.
// Meant to run every 15 minutes, e.g. via cron:
//   */15 * * * * docker compose exec -T app php bin/tick.php

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/MeasurementRepository.php';
require_once __DIR__ . '/../src/Simulator.php';

$repository = new MeasurementRepository(Database::connection());
$latest     = $repository->findLatest();

if ($latest === null) {
    fwrite(STDERR, "No data yet. Run bin/generate-data.php first.\n");
    exit(1);
}

// Continue with the battery state of the last measurement
$simulator = new Simulator((float) $latest['battery_soc']);
$from      = new DateTimeImmutable($latest['measured_at']);
$to        = Simulator::floorToInterval(new DateTimeImmutable('now'));
$count     = 0;

foreach ($simulator->measurementsBetween($from, $to) as $measurement) {
    $repository->insert($measurement);
    $count++;
}

echo "Added $count measurement(s). Latest: {$to->format('Y-m-d H:i')}\n";
