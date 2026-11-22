<?php

declare(strict_types=1);

/**
 * Creates a single shared PDO connection.
 * Credentials come from environment variables, which docker-compose
 * loads from the .env file (env_file).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                self::env('DB_HOST'),
                self::env('DB_PORT', '3306'),
                self::env('DB_NAME')
            );

            self::$pdo = new PDO($dsn, self::env('DB_USER'), self::env('DB_PASSWORD'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw on SQL errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows as associative arrays
                PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
            ]);
        }

        return self::$pdo;
    }

    private static function env(string $name, ?string $default = null): string
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            if ($default === null) {
                throw new RuntimeException("Missing environment variable: $name");
            }
            return $default;
        }

        return $value;
    }
}
