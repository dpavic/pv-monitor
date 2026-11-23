// Shared helpers for all pages: German number and date formatting.

const Format = {
    number: function (value, decimals) {
        return value.toLocaleString('de-DE', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    },

    // Watt -> "1,23" (kW)
    kw: function (watt) {
        return Format.number(watt / 1000, 2);
    },

    // null (no value) -> "–"
    orDash: function (value, decimals) {
        return value === null ? '–' : Format.number(value, decimals);
    },

    // "2026-11-23 19:45:00" -> "23.11.2026, 19:45 Uhr"
    // Split the string instead of new Date(), so the browser time zone does not matter
    dateTime: function (value) {
        const [date, time] = value.split(' ');
        const [year, month, day] = date.split('-');

        return day + '.' + month + '.' + year + ', ' + time.substring(0, 5) + ' Uhr';
    }
};

// Date helpers working with "YYYY-MM-DD" strings in local time
const Dates = {
    format: function (date) {
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return date.getFullYear() + '-' + month + '-' + day;
    },

    today: function () {
        return Dates.format(new Date());
    },

    // "2026-11-23", -1 -> "2026-11-22" (Date handles month and year changes)
    addDays: function (value, days) {
        const [year, month, day] = value.split('-').map(Number);

        return Dates.format(new Date(year, month - 1, day + days));
    }
};
