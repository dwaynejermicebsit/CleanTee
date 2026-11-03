<?php
declare(strict_types=1);

/** Open a new PDO connection to the CLEANTEE database. */
function cleantee_db(): PDO
{
    $config = require __DIR__ . '/config.php';
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'], $config['port'], $config['database']);

    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
