-- Executed automatically by the MySQL container on the first start
-- (only when the data volume is still empty).

CREATE TABLE IF NOT EXISTS measurements (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    measured_at     DATETIME     NOT NULL,              -- start of the 15-minute interval
    pv_power_w      INT          NOT NULL DEFAULT 0,    -- solar production
    load_power_w    INT          NOT NULL DEFAULT 0,    -- house consumption
    battery_power_w INT          NOT NULL DEFAULT 0,    -- + charging / - discharging
    battery_soc     DECIMAL(5,2) NOT NULL DEFAULT 0,    -- state of charge in %
    grid_power_w    INT          NOT NULL DEFAULT 0,    -- + import from grid / - export to grid
    PRIMARY KEY (id),
    UNIQUE KEY uq_measured_at (measured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
