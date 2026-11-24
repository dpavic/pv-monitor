<?php

declare(strict_types=1);

/**
 * All SQL for the measurements table lives here.
 */
final class MeasurementRepository
{
    private const COLUMNS = 'measured_at, pv_power_w, load_power_w, battery_power_w, battery_soc, grid_power_w';

    // Energy totals in kWh. Each row is a 15-minute average power:
    // W * 0.25 h / 1000 = kWh, i.e. W / 4000
    private const ENERGY_SUMS = '
        COALESCE(SUM(pv_power_w), 0) / 4000                  AS pv_kwh,
        COALESCE(SUM(load_power_w), 0) / 4000                AS load_kwh,
        COALESCE(SUM(GREATEST(grid_power_w, 0)), 0) / 4000   AS grid_import_kwh,
        COALESCE(SUM(GREATEST(-grid_power_w, 0)), 0) / 4000  AS grid_export_kwh';

    private ?PDOStatement $insertStatement = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function insert(array $m): void
    {
        // Prepare once, execute many times (fast for thousands of rows)
        $this->insertStatement ??= $this->pdo->prepare(
            'INSERT INTO measurements (' . self::COLUMNS . ')
             VALUES (:measured_at, :pv_power_w, :load_power_w, :battery_power_w, :battery_soc, :grid_power_w)'
        );

        $this->insertStatement->execute([
            'measured_at'     => $m['measured_at'],
            'pv_power_w'      => $m['pv_power_w'],
            'load_power_w'    => $m['load_power_w'],
            'battery_power_w' => $m['battery_power_w'],
            'battery_soc'     => $m['battery_soc'],
            'grid_power_w'    => $m['grid_power_w'],
        ]);
    }

    public function deleteAll(): void
    {
        $this->pdo->exec('TRUNCATE TABLE measurements');
    }

    public function findLatest(): ?array
    {
        $row = $this->pdo
            ->query('SELECT ' . self::COLUMNS . ' FROM measurements ORDER BY measured_at DESC LIMIT 1')
            ->fetch();

        return $row === false ? null : $this->castRow($row);
    }

    /**
     * All measurements of one day, ordered by time.
     */
    public function findByDay(DateTimeImmutable $day): array
    {
        // A range on the column itself (instead of DATE(measured_at) = ?) can use the index
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . '
             FROM measurements
             WHERE measured_at >= :start AND measured_at < :end
             ORDER BY measured_at'
        );
        $statement->execute($this->dayRange($day));

        return array_map([$this, 'castRow'], $statement->fetchAll());
    }

    /**
     * Energy totals of one day in kWh.
     */
    public function daySummary(DateTimeImmutable $day): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::ENERGY_SUMS . '
             FROM measurements
             WHERE measured_at >= :start AND measured_at < :end'
        );
        $statement->execute($this->dayRange($day));

        return $this->castTotals($statement->fetch());
    }

    /**
     * Energy totals per day in the range [$start, $end), keyed by "YYYY-MM-DD".
     */
    public function dailyTotals(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        return $this->totalsPerPeriod("DATE_FORMAT(measured_at, '%Y-%m-%d')", $start, $end);
    }

    /**
     * Energy totals per month in the range [$start, $end), keyed by "YYYY-MM".
     */
    public function monthlyTotals(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        return $this->totalsPerPeriod("DATE_FORMAT(measured_at, '%Y-%m')", $start, $end);
    }

    /**
     * $periodSql is always one of the fixed expressions above, never user input,
     * so it is safe to put it into the query string.
     */
    private function totalsPerPeriod(string $periodSql, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . $periodSql . ' AS period, ' . self::ENERGY_SUMS . '
             FROM measurements
             WHERE measured_at >= :start AND measured_at < :end
             GROUP BY period
             ORDER BY period'
        );
        $statement->execute([
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
        ]);

        $totals = [];
        foreach ($statement->fetchAll() as $row) {
            $period          = $row['period'];
            unset($row['period']);
            $totals[$period] = $this->castTotals($row);
        }

        return $totals;
    }

    private function dayRange(DateTimeImmutable $day): array
    {
        return [
            'start' => $day->format('Y-m-d 00:00:00'),
            'end'   => $day->modify('+1 day')->format('Y-m-d 00:00:00'),
        ];
    }

    /**
     * MySQL returns SUM() results as DECIMAL strings.
     */
    private function castTotals(array $row): array
    {
        return array_map(fn ($value) => round((float) $value, 2), $row);
    }

    /**
     * PDO returns DECIMAL columns as strings, so cast to proper JSON types.
     */
    private function castRow(array $row): array
    {
        return [
            'measured_at'     => $row['measured_at'],
            'pv_power_w'      => (int) $row['pv_power_w'],
            'load_power_w'    => (int) $row['load_power_w'],
            'battery_power_w' => (int) $row['battery_power_w'],
            'battery_soc'     => (float) $row['battery_soc'],
            'grid_power_w'    => (int) $row['grid_power_w'],
        ];
    }
}
