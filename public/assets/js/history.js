// History page: column chart per day (month view) or per month (year view) with Highcharts.

$(function () {
    // German texts and number format for all Highcharts charts
    Highcharts.setOptions({
        lang: {
            decimalPoint: ',',
            thousandsSep: '.',
            months: ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli',
                'August', 'September', 'Oktober', 'November', 'Dezember'],
            shortMonths: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul',
                'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
            weekdays: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
            resetZoom: 'Zoom zurücksetzen',
            contextButtonTitle: 'Diagramm exportieren',
            viewFullscreen: 'Vollbild',
            exitFullscreen: 'Vollbild beenden',
            printChart: 'Drucken',
            downloadPNG: 'Als PNG herunterladen',
            downloadSVG: 'Als SVG herunterladen',
            downloadCSV: 'Als CSV herunterladen',
            downloadXLS: 'Als XLS herunterladen',
            loading: 'Lädt …'
        }
    });

    const $section = $('#history');
    let range = $section.attr('data-range'); // "month" | "year"
    let date = $section.attr('data-date');   // any date inside the shown period
    let periods = [];
    let request = null;

    const chart = createChart();
    load();

    // ----- Navigation -----

    $('[data-range-button]').on('click', function () {
        range = $(this).attr('data-range-button');
        load();
    });

    $('#period-prev').on('click', function () {
        date = shiftPeriod(date, -1);
        load();
    });

    $('#period-next').on('click', function () {
        date = shiftPeriod(date, 1);
        load();
    });

    // First day of the previous (-1) or next (+1) month / year
    function shiftPeriod(value, step) {
        const [year, month] = value.split('-').map(Number);
        const shifted = range === 'month'
            ? new Date(year, month - 1 + step, 1)
            : new Date(year + step, 0, 1);

        return Dates.format(shifted);
    }

    // ----- Loading data -----

    function load() {
        $('[data-range-button]').each(function () {
            $(this).attr('aria-pressed', $(this).attr('data-range-button') === range ? 'true' : 'false');
        });

        if (request !== null) {
            request.abort();
        }

        chart.showLoading();

        request = $.getJSON('api/history.php', { range: range, date: date })
            .done(function (data) {
                date = data.start; // first day of the period, as calculated by the server
                periods = data.periods;

                history.replaceState(null, '', '?range=' + range + '&date=' + date);

                renderTitle();
                renderSummary(data.summary);
                renderChart();
                $('#history-error').prop('hidden', true);
            })
            .fail(function (xhr, status) {
                if (status !== 'abort') {
                    $('#history-error').prop('hidden', false);
                }
            })
            .always(function () {
                request = null;
                chart.hideLoading();
            });
    }

    // ----- Rendering -----

    function renderTitle() {
        const [year, month] = date.split('-').map(Number);
        const months = Highcharts.getOptions().lang.months;

        $('#period-title').text(range === 'month' ? months[month - 1] + ' ' + year : String(year));

        // No "next" into the future
        $('#period-next').prop('disabled', shiftPeriod(date, 1) > Dates.today());
    }

    function renderSummary(s) {
        const hasData = s.pv_kwh > 0 || s.load_kwh > 0;

        $('#sum-pv').text(hasData ? Format.number(s.pv_kwh, 0) : '–');
        $('#sum-load').text(hasData ? Format.number(s.load_kwh, 0) : '–');
        $('#sum-self-consumption').text(Format.orDash(s.self_consumption_percent, 0));
        $('#sum-self-sufficiency').text(Format.orDash(s.self_sufficiency_percent, 0));
    }

    function renderChart() {
        const shortMonths = Highcharts.getOptions().lang.shortMonths;

        // Month view: "1", "2", … day of month; year view: "Jan", "Feb", …
        const categories = periods.map(function (p) {
            return range === 'month'
                ? String(Number(p.period.substring(8, 10)))
                : shortMonths[Number(p.period.substring(5, 7)) - 1];
        });

        chart.xAxis[0].setCategories(categories, false);
        chart.xAxis[0].setExtremes(null, null, false); // reset zoom
        chart.series[0].setData(periods.map(p => p.pv_kwh), false);
        chart.series[1].setData(periods.map(p => p.load_kwh), false);
        chart.series[2].setData(periods.map(p => p.grid_export_kwh), false);
        chart.series[3].setData(periods.map(p => p.grid_import_kwh), false);
        chart.xAxis[0].setTitle({ text: range === 'month' ? 'Tag' : 'Monat' }, false);
        chart.redraw(); // draw once after all changes
    }

    // Click on a column: year view -> that month, month view -> that day on the overview page
    function openPeriod(index) {
        const period = periods[index].period;

        if (range === 'year') {
            range = 'month';
            date = period + '-01';
            load();
        } else {
            window.location.href = 'index.php?date=' + encodeURIComponent(period);
        }
    }

    function createChart() {
        return Highcharts.chart('history-chart', {
            chart: {
                type: 'column',
                zooming: { type: 'x' }, // select an area with the mouse (or pinch) to zoom
                style: { fontFamily: 'inherit' }
            },
            title: { text: null },
            credits: { enabled: false },
            colors: ['#e8a400', '#475569', '#22a05b', '#2563eb'],
            xAxis: {
                categories: [],
                title: { text: 'Tag' }
            },
            yAxis: {
                min: 0,
                title: { text: 'Energie (kWh)' }
            },
            tooltip: {
                shared: true, // all four values of a day in one tooltip
                valueDecimals: 1,
                valueSuffix: ' kWh'
            },
            legend: {
                verticalAlign: 'bottom'
            },
            plotOptions: {
                column: {
                    groupPadding: 0.1,
                    pointPadding: 0.05,
                    borderWidth: 0,
                    cursor: 'pointer',
                    point: {
                        events: {
                            click: function () {
                                openPeriod(this.index);
                            }
                        }
                    }
                }
            },
            series: [
                { name: 'Erzeugung', data: [] },
                { name: 'Verbrauch', data: [] },
                { name: 'Einspeisung', data: [] },
                { name: 'Netzbezug', data: [] }
            ],
            exporting: {
                fallbackToExportServer: false, // never send data to an external server
                filename: 'pv-monitor-verlauf',
                buttons: {
                    contextButton: {
                        menuItems: ['viewFullscreen', 'printChart', 'separator',
                            'downloadPNG', 'downloadSVG', 'separator',
                            'downloadCSV', 'downloadXLS']
                    }
                }
            },
            responsive: {
                rules: [{
                    // Small screens: smaller legend, no axis title
                    condition: { maxWidth: 500 },
                    chartOptions: {
                        legend: { itemStyle: { fontSize: '11px' } },
                        yAxis: { title: { text: null } }
                    }
                }]
            }
        });
    }
});
