// Overview page: live cards (refreshed every 60 s), daily totals and daily chart.

$(function () {
    const REFRESH_INTERVAL_MS = 60 * 1000;
    const MIN_FLOW_W = 10; // smaller flows are shown as inactive

    const $dayInput = $('#day-input');
    let dayChart = null;
    let dayRequest = null;

    loadLive();
    loadDay();

    setInterval(function () {
        loadLive();

        // New measurements only arrive for today
        if ($dayInput.val() === Dates.today()) {
            loadDay();
        }
    }, REFRESH_INTERVAL_MS);

    // ----- Day navigation -----

    $('#day-prev').on('click', function () {
        showDay(Dates.addDays($dayInput.val(), -1));
    });

    $('#day-next').on('click', function () {
        showDay(Dates.addDays($dayInput.val(), 1));
    });

    $('#day-today').on('click', function () {
        showDay(Dates.today());
    });

    $dayInput.on('change', function () {
        if ($dayInput.val() !== '') {
            showDay($dayInput.val());
        }
    });

    function showDay(date) {
        $dayInput.val(date);

        // Keep the date in the URL, so a reload or a shared link shows the same day
        history.replaceState(null, '', '?date=' + encodeURIComponent(date));

        loadDay();
    }

    // ----- Live values -----

    function loadLive() {
        $.getJSON('api/live.php')
            .done(function (data) {
                renderLive(data.measurement);
                $('#live-error').prop('hidden', true);
            })
            .fail(function () {
                $('#live-error').prop('hidden', false);
            });
    }

    function renderLive(m) {
        // .text() instead of .html(): values are never interpreted as HTML (XSS protection)
        $('#live-time').text('Stand: ' + Format.dateTime(m.measured_at));
        $('#live-pv').text(Format.kw(m.pv_power_w));
        $('#live-load').text(Format.kw(m.load_power_w));
        $('#live-soc').text(Format.number(m.battery_soc, 0));
        $('#live-grid').text(Format.kw(Math.abs(m.grid_power_w)));

        let batteryState = 'Ruhezustand';
        if (m.battery_power_w > 0) {
            batteryState = 'Lädt mit ' + Format.kw(m.battery_power_w) + ' kW';
        } else if (m.battery_power_w < 0) {
            batteryState = 'Entlädt mit ' + Format.kw(-m.battery_power_w) + ' kW';
        }
        $('#live-battery-state').text(batteryState);

        let gridState = 'Kein Austausch';
        if (m.grid_power_w > 0) {
            gridState = 'Netzbezug';
        } else if (m.grid_power_w < 0) {
            gridState = 'Einspeisung';
        }
        $('#live-grid-state').text(gridState);

        renderFlow(m, gridState);
    }

    // ----- Energy flow -----

    function renderFlow(m, gridState) {
        // Split the four measured values into the five possible flows (in W).
        // In this system the battery is only charged from solar, never from the grid.
        const flows = {
            'solar-home': Math.min(m.pv_power_w, m.load_power_w),
            'solar-battery': Math.max(m.battery_power_w, 0),
            'solar-grid': Math.max(-m.grid_power_w, 0),
            'grid-home': Math.max(m.grid_power_w, 0),
            'battery-home': Math.max(-m.battery_power_w, 0)
        };

        $.each(flows, function (name, watt) {
            const $line = $('[data-flow="' + name + '"]');
            const isActive = watt >= MIN_FLOW_W;

            $line.toggleClass('is-active', isActive);

            if (isActive) {
                $line.css('animation-duration', flowDuration(watt) + 's');
            }
        });

        $('#flow-solar').text(Format.kw(m.pv_power_w) + ' kW');
        $('#flow-home').text(Format.kw(m.load_power_w) + ' kW');
        $('#flow-grid').text(Format.kw(Math.abs(m.grid_power_w)) + ' kW');
        $('#flow-grid-state').text(m.grid_power_w === 0 ? '' : gridState);
        $('#flow-battery').text(Format.kw(Math.abs(m.battery_power_w)) + ' kW');
        $('#flow-battery-state').text('Ladezustand ' + Format.number(m.battery_soc, 0) + ' %');

        // Text alternative for screen readers
        $('#flow-desc').text(
            'Solar ' + Format.kw(m.pv_power_w) + ' kW, ' +
            'Hausverbrauch ' + Format.kw(m.load_power_w) + ' kW, ' +
            'Batterie ' + Format.kw(m.battery_power_w) + ' kW bei ' + Format.number(m.battery_soc, 0) + ' %, ' +
            gridState + ' ' + Format.kw(Math.abs(m.grid_power_w)) + ' kW.'
        );
    }

    // More power = faster moving dots: 2 s per cycle at 0 kW, down to 0.5 s from 5 kW
    function flowDuration(watt) {
        return Math.max(0.5, 2 - watt / 1000 * 0.3).toFixed(2);
    }

    // ----- Day data -----

    function loadDay() {
        const date = $dayInput.val();

        // "Next" makes no sense for today or later
        $('#day-next').prop('disabled', date >= Dates.today());

        // Cancel a still running request, so a slow old answer cannot overwrite a newer day
        if (dayRequest !== null) {
            dayRequest.abort();
        }

        dayRequest = $.getJSON('api/day.php', { date: date })
            .done(function (data) {
                renderSummary(data.summary);
                renderChart(data.measurements);
                $('#day-empty').prop('hidden', data.measurements.length > 0);
                $('#day-error').prop('hidden', true);
            })
            .fail(function (xhr, status) {
                if (status !== 'abort') {
                    $('#day-error').prop('hidden', false);
                }
            })
            .always(function () {
                dayRequest = null;
            });
    }

    function renderSummary(s) {
        const hasData = s.pv_kwh > 0 || s.load_kwh > 0;

        $('#day-pv').text(hasData ? Format.number(s.pv_kwh, 1) : '–');
        $('#day-load').text(hasData ? Format.number(s.load_kwh, 1) : '–');
        $('#day-self-consumption').text(Format.orDash(s.self_consumption_percent, 0));
        $('#day-self-sufficiency').text(Format.orDash(s.self_sufficiency_percent, 0));
    }

    function renderChart(measurements) {
        const labels = measurements.map(m => m.measured_at.substring(11, 16)); // "HH:MM"
        const pv = measurements.map(m => m.pv_power_w / 1000);
        const load = measurements.map(m => m.load_power_w / 1000);
        const soc = measurements.map(m => m.battery_soc);

        // Chart already exists: only replace the data (smooth animation, no flicker)
        if (dayChart !== null) {
            dayChart.data.labels = labels;
            dayChart.data.datasets[0].data = pv;
            dayChart.data.datasets[1].data = load;
            dayChart.data.datasets[2].data = soc;
            dayChart.update();
            return;
        }

        dayChart = new Chart(document.getElementById('day-chart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Erzeugung',
                        data: pv,
                        yAxisID: 'power',
                        borderColor: '#e8a400',
                        backgroundColor: 'rgba(245, 183, 0, 0.25)',
                        fill: true
                    },
                    {
                        label: 'Verbrauch',
                        data: load,
                        yAxisID: 'power',
                        borderColor: '#475569',
                        backgroundColor: 'rgba(71, 85, 105, 0.15)',
                        fill: true
                    },
                    {
                        label: 'Batterie (SoC)',
                        data: soc,
                        yAxisID: 'soc',
                        borderColor: '#15803d',
                        borderDash: [6, 4],
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // height comes from CSS (.chart-box)
                interaction: { mode: 'index', intersect: false }, // tooltip shows all values of a time
                elements: {
                    point: { radius: 0, hoverRadius: 4 },
                    line: { borderWidth: 2, tension: 0.3 }
                },
                scales: {
                    x: {
                        ticks: { maxTicksLimit: 12 }
                    },
                    power: {
                        position: 'left',
                        beginAtZero: true,
                        title: { display: true, text: 'Leistung (kW)' }
                    },
                    soc: {
                        position: 'right',
                        min: 0,
                        max: 100,
                        title: { display: true, text: 'Ladezustand (%)' },
                        grid: { drawOnChartArea: false } // only one grid, from the left axis
                    }
                },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const isSoc = context.dataset.yAxisID === 'soc';
                                const value = isSoc
                                    ? Format.number(context.parsed.y, 0) + ' %'
                                    : Format.number(context.parsed.y, 2) + ' kW';

                                return context.dataset.label + ': ' + value;
                            }
                        }
                    }
                }
            }
        });
    }
});
