<?php

declare(strict_types=1);

// Overview page: live values, daily totals and the daily chart.
// The page itself contains no data – it is loaded via AJAX from /api.

require_once __DIR__ . '/../src/bootstrap.php';

$today = date('Y-m-d');

// Day to show (from ?date=…), today if missing or invalid
$selectedDay = parse_date($_GET['date'] ?? '')?->format('Y-m-d') ?? $today;

$pageTitle   = 'Übersicht';
$activePage  = 'overview';
$pageScripts = [
    [
        'src'       => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
        'integrity' => 'sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4',
    ],
    ['src' => asset('js/overview.js')],
];

require __DIR__ . '/../templates/header.php';
?>

<section class="section" aria-labelledby="live-title">
    <div class="section__head">
        <h2 id="live-title">Aktuell</h2>
        <span class="muted" id="live-time">–</span>
    </div>

    <div class="cards">
        <article class="card card--pv">
            <h3 class="card__label">Photovoltaik</h3>
            <p class="card__value"><span id="live-pv">–</span> <small>kW</small></p>
            <p class="card__info">Erzeugung</p>
        </article>

        <article class="card card--load">
            <h3 class="card__label">Verbrauch</h3>
            <p class="card__value"><span id="live-load">–</span> <small>kW</small></p>
            <p class="card__info">Hausverbrauch</p>
        </article>

        <article class="card card--battery">
            <h3 class="card__label">Batterie</h3>
            <p class="card__value"><span id="live-soc">–</span> <small>%</small></p>
            <p class="card__info" id="live-battery-state">–</p>
        </article>

        <article class="card card--grid">
            <h3 class="card__label">Netz</h3>
            <p class="card__value"><span id="live-grid">–</span> <small>kW</small></p>
            <p class="card__info" id="live-grid-state">–</p>
        </article>
    </div>

    <p class="alert" id="live-error" hidden>Live-Daten konnten nicht geladen werden.</p>
</section>

<section class="section" aria-labelledby="day-title">
    <div class="section__head">
        <h2 id="day-title">Tagesverlauf</h2>

        <div class="date-nav">
            <button type="button" class="button" id="day-prev" aria-label="Vorheriger Tag">◀</button>
            <input type="date" class="date-nav__input" id="day-input" aria-label="Datum"
                   value="<?= e($selectedDay) ?>" max="<?= e($today) ?>">
            <button type="button" class="button" id="day-next" aria-label="Nächster Tag">▶</button>
            <button type="button" class="button button--light" id="day-today">Heute</button>
        </div>
    </div>

    <div class="stats">
        <div class="stat">
            <span class="stat__label">Ertrag</span>
            <span class="stat__value"><span id="day-pv">–</span> <small>kWh</small></span>
        </div>
        <div class="stat">
            <span class="stat__label">Verbrauch</span>
            <span class="stat__value"><span id="day-load">–</span> <small>kWh</small></span>
        </div>
        <div class="stat">
            <span class="stat__label">Eigenverbrauchsquote</span>
            <span class="stat__value"><span id="day-self-consumption">–</span> <small>%</small></span>
        </div>
        <div class="stat">
            <span class="stat__label">Autarkiegrad</span>
            <span class="stat__value"><span id="day-self-sufficiency">–</span> <small>%</small></span>
        </div>
    </div>

    <div class="chart-box">
        <canvas id="day-chart" aria-label="Tagesverlauf von Erzeugung, Verbrauch und Batterieladung" role="img"></canvas>
    </div>

    <p class="muted" id="day-empty" hidden>Für diesen Tag sind keine Messwerte vorhanden.</p>
    <p class="alert" id="day-error" hidden>Tagesdaten konnten nicht geladen werden.</p>
</section>

<?php require __DIR__ . '/../templates/footer.php'; ?>
