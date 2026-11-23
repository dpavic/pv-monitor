<?php

declare(strict_types=1);

/**
 * All SQL for the measurements table lives here.
 */
final class MeasurementRepository
{
    private ?PDOStatement $insertStatement = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function insert(array $m): void
    {
        // Prepare once, execute many times (fast for thousands of rows)
        $this->insertStatement ??= $this->pdo->prepare(
            'INSERT INTO measurements
                (measured_at, pv_power_w, load_power_w, battery_power_w, battery_soc, grid_power_w)
             VALUES
                (:measured_at, :pv_power_w, :load_power_w, :battery_power_w, :battery_soc, :grid_power_w)'
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
            ->query('SELECT * FROM measurements ORDER BY measured_at DESC LIMIT 1')
            ->fetch();

        return $row === false ? null : $row;
    }
}
