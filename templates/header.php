<?php
/**
 * Page header and navigation.
 * Expects: $pageTitle (string), $activePage ('overview' | 'history')
 */

$navItems = [
    'overview' => ['url' => 'index.php',   'label' => 'Übersicht'],
    'history'  => ['url' => 'verlauf.php', 'label' => 'Verlauf'],
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> – PV-Monitor</title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="index.php">
            <span class="brand__icon" aria-hidden="true">☀</span> PV-Monitor
        </a>
        <nav class="main-nav" aria-label="Hauptnavigation">
            <?php foreach ($navItems as $key => $item): ?>
                <a href="<?= e($item['url']) ?>"
                   <?= $key === $activePage ? 'class="is-active" aria-current="page"' : '' ?>>
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<main class="container">
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
