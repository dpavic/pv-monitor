<?php

declare(strict_types=1);

// GET /api/live.php
// Returns the latest measurement.

require_once __DIR__ . '/../../src/bootstrap.php';

require_get_request();

try {
    $latest = (new MeasurementRepository(Database::connection()))->findLatest();
} catch (Throwable $e) {
    error_log('api/live.php: ' . $e->getMessage());
    json_response(['error' => 'Interner Fehler.'], 500);
}

if ($latest === null) {
    json_response(['error' => 'Keine Messwerte vorhanden.'], 404);
}

json_response(['measurement' => $latest]);
