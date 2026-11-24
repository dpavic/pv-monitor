<?php

declare(strict_types=1);

// History page: energy totals per day of a month or per month of a year (Highcharts).
// Data is loaded via AJAX from /api/history.php.

require_once __DIR__ . '/../src/bootstrap.php';

$range = in_array($_GET['range'] ?? '', ['month', 'year'], true) ? $_GET['range'] : 'month';
$date  = parse_date($_GET['date'] ?? '')?->format('Y-m-d') ?? date('Y-m-d');

$highcharts = 'https://cdn.jsdelivr.net/npm/highcharts@11.4.8/';

$pageTitle   = 'Verlauf';
$activePage  = 'history';
$pageScripts = [
    [
        'src'       => $highcharts . 'highcharts.js',
        'integrity' => 'sha384-XxsZMNol0nU0LnC2cYsyhRNbfyK7CPmF7WrlFvZDdlCKn9hzVkXsHxCHVaKwkl76',
    ],
    [
        'src'       => $highcharts . 'modules/exporting.js',
        'integrity' => 'sha384-iIafvPxt7arZGSNeMKRXbEr8N+ijzanJ60dtD21K9navK1tFiY0vuOeGVpIjPD9S',
    ],
    [
        'src'       => $highcharts . 'modules/export-data.js',
        'integrity' => 'sha384-kC2p0b+8EL/jjvtMJB4TdQ/g3aKSiD+u+pny5RCpHryzWAyog2fhvuv4zf6yxgFh',
    ],
    [
        // Creates PNG/SVG files in the browser instead of sending the data to the Highcharts server
        'src'       => $highcharts . 'modules/offline-exporting.js',
        'integrity' => 'sha384-Lj2ktmBW/0wsRwmnbE6m6gp//rkDRovWuhjZP9qbWmPQ3WLDfgW3BZQYiitV37fX',
    ],
    [
        'src'       => $highcharts . 'modules/accessibility.js',
        'integrity' => 'sha384-RFRqB325VNHIIuDAhM+VeFF+SkZwLBzZioBGl5Eklzl88CWphk90Q7ACAcOt8R8R',
    ],
    ['src' => asset('js/history.js')],
];

require __DIR__ . '/../templates/header.php';
?>

<section class="section" id="history" data-range="<?= e($range) ?>" data-date="<?= e($date) ?>">
    <div class="section__head section__head--controls">
        <div class="segmented" role="group" aria-label="Zeitraum">
            <button type="button" class="segmented__button" data-range-button="month">Monat</button>
            <button type="button" class="segmented__button" data-range-button="year">Jahr</button>
        </div>

        <div class="date-nav">
            <button type="button" class="button" id="period-prev" aria-label="Vorheriger Zeitraum">◀</button>
            <h2 class="date-nav__title" id="period-title" aria-live="polite">–</h2>
            <button type="button" class="button" id="period-next" aria-label="Nächster Zeitraum">▶</button>
        </div>
    </div>

    <div class="stats">
        <div class="stat">
            <span class="stat__label">Ertrag</span>
            <span class="stat__value"><span id="sum-pv">–</span> <small>kWh</small></span>
        </div>
        <div class="stat">
            <span class="stat__label">Verbrauch</span>
            <span class="stat__value"><span id="sum-load">–</span> <small>kWh</small></span>
        </div>
        <div class="stat">
            <span class="stat__label">Eigenverbrauchsquote</span>
            <span class="stat__value"><span id="sum-self-consumption">–</span> <small>%</small></span>
        </div>
        <div class="stat">
            <span class="stat__label">Autarkiegrad</span>
            <span class="stat__value"><span id="sum-self-sufficiency">–</span> <small>%</small></span>
        </div>
    </div>

    <div class="chart-box chart-box--highcharts" id="history-chart"></div>
    <p class="muted">Tipp: Bereich im Diagramm markieren zum Zoomen, auf eine Säule klicken für Details.</p>

    <p class="alert" id="history-error" hidden>Verlaufsdaten konnten nicht geladen werden.</p>
</section>

<?php require __DIR__ . '/../templates/footer.php'; ?>
