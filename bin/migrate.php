<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    fwrite(STDERR, "Interdit\n");
    exit(1);
}

require_once dirname(__DIR__) . '/src/env.php';
require_once dirname(__DIR__) . '/src/Db.php';

$name = (string) ($_ENV['DB_NAME'] ?? '');
if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
    fwrite(STDERR, "DB_NAME invalide.\n");
    exit(1);
}

try {
    $server = Db::serverPdo();
    $server->exec(
        'CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    fwrite(STDOUT, "Base {$name} prête.\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'Création de la base ignorée : ' . $e->getMessage() . "\n");
}

$pdo = Db::pdo();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(190) NOT NULL,
        applied_at DATETIME NOT NULL,
        PRIMARY KEY (version)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$files = glob(dirname(__DIR__) . '/db/migrations/*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $version = basename($file);
    $exists = $pdo->prepare('SELECT version FROM schema_migrations WHERE version = ?');
    $exists->execute([$version]);
    if ($exists->fetchColumn()) {
        fwrite(STDOUT, "{$version} déjà appliquée.\n");
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Lecture impossible : {$version}\n");
        exit(1);
    }

    $parts = preg_split('/;\s*(?:\R|$)/', trim($sql)) ?: [];
    try {
        foreach ($parts as $part) {
            $statement = trim($part);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "{$version} en échec : " . $e->getMessage() . "\n");
        exit(1);
    }

    $insert = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)');
    $insert->execute([$version, date('Y-m-d H:i:s')]);
    fwrite(STDOUT, "{$version} appliquée.\n");
}
