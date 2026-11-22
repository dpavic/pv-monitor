<?php

declare(strict_types=1);

// Temporary page for step 1: checks the database connection.
// Will be replaced by the real overview page in step 4.

require_once __DIR__ . '/../src/Database.php';

try {
    $pdo     = Database::connection();
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $count   = $pdo->query('SELECT COUNT(*) FROM measurements')->fetchColumn();
    $status  = "Verbindung erfolgreich – MySQL $version, $count Messwerte in der Datenbank.";
    $ok      = true;
} catch (Throwable $e) {
    // Log details for the developer, show only a generic message to the user
    error_log('Database check failed: ' . $e->getMessage());
    $status = 'Keine Verbindung zur Datenbank möglich.';
    $ok     = false;
}

http_response_code($ok ? 200 : 500);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PV-Monitor – Verbindungstest</title>
</head>
<body>
    <h1>PV-Monitor</h1>
    <p style="color: <?= $ok ? 'green' : 'red' ?>">
        <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
    </p>
</body>
</html>
