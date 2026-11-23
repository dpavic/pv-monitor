<?php

declare(strict_types=1);

/**
 * Generates realistic 15-minute measurements for a home with
 * a 10 kWp PV plant and a 10 kWh battery in Lower Bavaria.
 *
 * Energy balance for every interval:  pv + grid = load + battery
 */
final class Simulator
{
    public const INTERVAL_MINUTES = 15;
    private const INTERVAL_HOURS  = self::INTERVAL_MINUTES / 60;

    // PV plant
    private const PEAK_POWER_W      = 10000; // 10 kWp
    private const SYSTEM_EFFICIENCY = 0.85;  // inverter, cable and temperature losses
    private const LATITUDE          = 48.4;  // Lower Bavaria
    // Sun is highest at about 12:10 CET here (longitude ~12.5° E instead of 15° E)
    private const SOLAR_NOON_CET    = 12 + 10 / 60;

    // Battery
    private const BATTERY_CAPACITY_WH = 10000; // 10 kWh
    private const BATTERY_MAX_POWER_W = 5000;  // max. charge/discharge power
    private const BATTERY_MIN_SOC     = 5.0;   // reserve that is never used

    private float $soc;

    public function __construct(float $startSoc = 50.0)
    {
        $this->soc = $startSoc;
    }

    /**
     * Yields one measurement per interval in the range ($from, $to].
     * $from itself is excluded, so tick.php can continue after the last stored row.
     */
    public function measurementsBetween(DateTimeImmutable $from, DateTimeImmutable $to): Generator
    {
        $lastKey = $from->format('Y-m-d H:i:s');
        $time    = $from->modify('+' . self::INTERVAL_MINUTES . ' minutes');

        while ($time <= $to) {
            $key = $time->format('Y-m-d H:i:s');

            // When summer time ends, local clock time 02:00–03:00 occurs twice.
            // measured_at is unique, so the repeated hour is skipped.
            if ($key > $lastKey) {
                yield $this->measurementAt($time);
                $lastKey = $key;
            }

            $time = $time->modify('+' . self::INTERVAL_MINUTES . ' minutes');
        }
    }

    /**
     * Rounds a time down to the start of its 15-minute interval.
     */
    public static function floorToInterval(DateTimeImmutable $time): DateTimeImmutable
    {
        $minute = intdiv((int) $time->format('i'), self::INTERVAL_MINUTES) * self::INTERVAL_MINUTES;

        return $time->setTime((int) $time->format('H'), $minute);
    }

    public function measurementAt(DateTimeImmutable $time): array
    {
        $pv      = $this->pvPower($time);
        $load    = $this->loadPower($time);
        $battery = $this->batteryPower($pv - $load);
        $grid    = $load + $battery - $pv; // whatever is left comes from / goes to the grid

        // Update the state of charge with the energy of this interval
        $this->soc += $battery * self::INTERVAL_HOURS / self::BATTERY_CAPACITY_WH * 100;
        $this->soc  = round(min(100, max(0, $this->soc)), 2);

        return [
            'measured_at'     => $time->format('Y-m-d H:i:s'),
            'pv_power_w'      => $pv,
            'load_power_w'    => $load,
            'battery_power_w' => $battery,
            'battery_soc'     => $this->soc,
            'grid_power_w'    => $grid,
        ];
    }

    private function pvPower(DateTimeImmutable $time): int
    {
        // Solar declination: angle of the sun north/south of the equator (-23.44° to +23.44°)
        $dayOfYear   = (int) $time->format('z') + 1;
        $declination = 23.44 * sin(deg2rad(360 / 365 * ($dayOfYear - 81)));

        // Sunrise equation: half of the day length in hours
        $cosHourAngle = -tan(deg2rad(self::LATITUDE)) * tan(deg2rad($declination));
        $halfDayHours = rad2deg(acos($cosHourAngle)) / 15; // the earth turns 15° per hour

        $solarNoon = self::SOLAR_NOON_CET + (int) $time->format('I'); // +1 h in summer time
        $sunrise   = $solarNoon - $halfDayHours;
        $sunset    = $solarNoon + $halfDayHours;

        // Use the middle of the interval
        $hour = (int) $time->format('G') + ((int) $time->format('i') + self::INTERVAL_MINUTES / 2) / 60;

        if ($hour <= $sunrise || $hour >= $sunset) {
            return 0;
        }

        // Sine curve from sunrise to sunset; squared to get the narrower bell of real PV data
        $dayCurve = sin(M_PI * ($hour - $sunrise) / ($sunset - $sunrise)) ** 2;

        // The higher the sun at noon, the stronger the radiation (summer ~65°, winter ~18°)
        $noonElevation = 90 - self::LATITUDE + $declination;
        $seasonFactor  = sin(deg2rad($noonElevation));

        // Cloudy days are darker and also more unsteady
        $clouds = $this->cloudFactor($time);
        $noise  = 1 + $this->randomBetween(-1, 1) * (0.05 + 0.35 * (1 - $clouds));

        $power = self::PEAK_POWER_W * self::SYSTEM_EFFICIENCY * $seasonFactor * $dayCurve * $clouds * $noise;

        return (int) round(min(self::PEAK_POWER_W, max(0, $power)));
    }

    /**
     * Weather factor for a day: 0.15 = very cloudy, 1.0 = clear sky.
     * It is derived from the date, so the same day always has the same weather
     * (generate-data.php and tick.php produce matching values).
     */
    private function cloudFactor(DateTimeImmutable $time): float
    {
        $random = (crc32($time->format('Y-m-d')) % 1000) / 999; // 0.0 … 1.0

        return 0.15 + 0.85 * sqrt($random); // sqrt: clear days are a bit more common
    }

    private function loadPower(DateTimeImmutable $time): int
    {
        $hour = (int) $time->format('G');

        $load = 300 + mt_rand(-40, 40); // base load: fridge, router, standby

        if ($hour >= 6 && $hour < 8) {
            $load += 500;  // morning peak: coffee machine, hair dryer, lights
        } elseif ($hour >= 8 && $hour < 18) {
            $load += 150;  // daytime: computer, small devices
        } elseif ($hour >= 18 && $hour < 22) {
            $load += 800;  // evening peak: cooking, lights, TV
        }

        // Occasional short spikes: washing machine, stove, kettle
        if ($hour >= 7 && $hour < 22 && mt_rand(1, 100) <= 5) {
            $load += mt_rand(1500, 2500);
        }

        return $load;
    }

    /**
     * Surplus > 0: charge the battery, surplus < 0: discharge it.
     * Limited by the max. battery power and by the free / available energy.
     */
    private function batteryPower(int $surplus): int
    {
        if ($surplus > 0) {
            $freeWh   = (100 - $this->soc) / 100 * self::BATTERY_CAPACITY_WH;
            $maxPower = min(self::BATTERY_MAX_POWER_W, $freeWh / self::INTERVAL_HOURS);

            return (int) min($surplus, max(0, $maxPower));
        }

        $availableWh = ($this->soc - self::BATTERY_MIN_SOC) / 100 * self::BATTERY_CAPACITY_WH;
        $maxPower    = min(self::BATTERY_MAX_POWER_W, $availableWh / self::INTERVAL_HOURS);

        return -(int) min(-$surplus, max(0, $maxPower));
    }

    private function randomBetween(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }
}
