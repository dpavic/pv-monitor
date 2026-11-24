<?php

declare(strict_types=1);

// Shared setup for all pages and API endpoints.

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/MeasurementRepository.php';

// Show PHP errors only during development, never to real users.
// Any other or missing APP_ENV counts as production (safe default).
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_ENV') === 'development' ? '1' : '0');

// Last safety net for exceptions nobody caught: log the details,
// show the user only a generic message
set_exception_handler(function (Throwable $e): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Interner Fehler. Bitte später erneut versuchen.';
});

/**
 * Escapes a value for safe output in HTML (protection against XSS).
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * URL of a file in public/assets with its modification time appended,
 * so the browser loads a new version as soon as the file changes.
 */
function asset(string $path): string
{
    $version = filemtime(__DIR__ . '/../public/assets/' . $path) ?: 0;

    return 'assets/' . $path . '?v=' . $version;
}

/**
 * Sends data as JSON and stops the script.
 */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store'); // measurements change, never cache them
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

/**
 * The API is read-only, so only GET requests are allowed.
 */
function require_get_request(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET');
        json_response(['error' => 'Methode nicht erlaubt.'], 405);
    }
}

/**
 * Adds self-consumption and self-sufficiency rates to energy totals (kWh).
 */
function with_ratios(array $totals): array
{
    // Self-consumption rate: share of the PV production used in the house (incl. battery)
    $totals['self_consumption_percent'] = percent(
        $totals['pv_kwh'] - $totals['grid_export_kwh'],
        $totals['pv_kwh']
    );

    // Self-sufficiency rate: share of the consumption NOT bought from the grid
    $totals['self_sufficiency_percent'] = percent(
        $totals['load_kwh'] - $totals['grid_import_kwh'],
        $totals['load_kwh']
    );

    return $totals;
}

/**
 * Part / total in percent, or null if the total is 0 (e.g. no sun at all).
 */
function percent(float $part, float $total): ?float
{
    return $total > 0 ? round($part / $total * 100, 1) : null;
}

/**
 * Parses a date in the format YYYY-MM-DD. Returns null for invalid input.
 */
function parse_date(mixed $value): ?DateTimeImmutable
{
    // ?date[]=x would give an array instead of a string
    if (!is_string($value)) {
        return null;
    }

    // "!" resets the time to 00:00:00
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    // createFromFormat() silently turns 2026-02-31 into 2026-03-03,
    // so the result must give back exactly the same string
    if ($date === false || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $date;
}
