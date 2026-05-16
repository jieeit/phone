#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run in CLI mode.\n");
    exit(1);
}

$autoloadPaths = array(
    dirname(dirname(__DIR__)) . '/vendor/autoload.php',
    dirname(dirname(dirname(__DIR__))) . '/autoload.php',
);

$autoloadLoaded = false;
for ($i = 0; $i < count($autoloadPaths); $i++) {
    if (is_file($autoloadPaths[$i])) {
        require $autoloadPaths[$i];
        $autoloadLoaded = true;
        break;
    }
}

if (!$autoloadLoaded) {
    fwrite(STDERR, "Autoload file not found. Run composer install first.\n");
    exit(1);
}

$options = getopt('', array(
    'host::',
    'port::',
    'user::',
    'password::',
    'database::',
    'table::',
    'output::',
    'help::',
));

if (isset($options['help'])) {
    $help = "Usage:\n"
        . "  php src/bin/build-phone-data.php [--host=127.0.0.1] [--port=3306] [--user=root] [--password=root] [--database=test] [--table=sa_phone_number_segment] [--output=src/data/phone.dat]\n";
    fwrite(STDOUT, $help);
    exit(0);
}

$host = isset($options['host']) ? $options['host'] : '127.0.0.1';
$port = isset($options['port']) ? $options['port'] : '3306';
$user = isset($options['user']) ? $options['user'] : 'root';
$password = isset($options['password']) ? $options['password'] : 'root';
$database = isset($options['database']) ? $options['database'] : 'test';
$table = isset($options['table']) ? $options['table'] : 'sa_phone_number_segment';
$output = isset($options['output']) ? $options['output'] : (dirname(__DIR__) . '/data/phone.dat');

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

try {
    $start = microtime(true);

    $pdo = new \PDO($dsn, $user, $password, array(
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ));

    $builder = new \Jieeit\Phone\Core\Binary\BinaryBuilder();
    $result = $builder->buildFromPdo($pdo, $table, $output);

    $cost = microtime(true) - $start;

    fwrite(STDOUT, "Build completed.\n");
    fwrite(STDOUT, "- total records: " . $result['total'] . "\n");
    fwrite(STDOUT, "- skipped records: " . $result['skipped'] . "\n");
    fwrite(STDOUT, "- output file: " . $result['output'] . "\n");
    fwrite(STDOUT, "- file size: " . $result['size'] . " bytes\n");
    fwrite(STDOUT, "- time: " . number_format($cost, 4) . "s\n");
    exit(0);
} catch (\PDOException $e) {
    fwrite(STDERR, "Database error: " . $e->getMessage() . "\n");
    exit(2);
} catch (\Exception $e) {
    fwrite(STDERR, "Build error: " . $e->getMessage() . "\n");
    exit(3);
}
