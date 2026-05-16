<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Jieeit\Phone\Core\Carrier\CarrierCatalog;
use Jieeit\Phone\Facade\Phone;

// 用法：
// php tests/test_db_consistency.php [--host=127.0.0.1] [--port=3306] [--user=root] [--password=root] [--database=test] [--table=sa_phone_number_segment] [--limit=1000]

$options = getopt('', array(
    'host::',
    'port::',
    'user::',
    'password::',
    'database::',
    'table::',
    'limit::',
));

$host = isset($options['host']) ? $options['host'] : '127.0.0.1';
$port = isset($options['port']) ? $options['port'] : '3306';
$user = isset($options['user']) ? $options['user'] : 'root';
$password = isset($options['password']) ? $options['password'] : 'root';
$database = isset($options['database']) ? $options['database'] : 'test';
$table = isset($options['table']) ? $options['table'] : 'sa_phone_number_segment';
$limit = isset($options['limit']) ? (int) $options['limit'] : 1000;

if ($limit <= 0) {
    fwrite(STDERR, "[FAIL] limit must be > 0\n");
    exit(1);
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

try {
    $pdo = new \PDO($dsn, $user, $password, array(
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ));

    $sql = sprintf(
        'SELECT phone, province, city, operator FROM `%s` ORDER BY RAND() LIMIT %d',
        str_replace('`', '', $table),
        $limit
    );

    $rows = $pdo->query($sql)->fetchAll();

    $total = count($rows);
    $passed = 0;
    $failed = 0;
    $errorCount = 0;

    for ($i = 0; $i < $total; $i++) {
        $row = $rows[$i];
        $phone7 = isset($row['phone']) ? trim((string) $row['phone']) : '';

        if (!preg_match('/^[0-9]{7}$/', $phone7)) {
            $failed++;
            fwrite(STDOUT, "[MISMATCH] invalid phone7 in DB: {$phone7}\n");
            continue;
        }

        $phone11 = $phone7 . '0000';
        $expected = buildExpected($phone11, $row);

        try {
            $actual = Phone::find($phone11);
        } catch (Exception $e) {
            $errorCount++;
            $failed++;
            fwrite(STDOUT, "[ERROR] {$phone11} API exception: {$e->getMessage()}\n");
            continue;
        }

        if (
            $actual['province'] === $expected['province']
            && $actual['city'] === $expected['city']
            && $actual['operator'] === $expected['operator']
            && $actual['virtual_operator'] === $expected['virtual_operator']
        ) {
            $passed++;
        } else {
            $failed++;
            fwrite(
                STDOUT,
                "[MISMATCH] {$phone11} expected={" . json_encode($expected, JSON_UNESCAPED_UNICODE) . "} actual={" . json_encode($actual, JSON_UNESCAPED_UNICODE) . "}\n"
            );
        }
    }

    fwrite(STDOUT, "\n=== DB Consistency Result ===\n");
    fwrite(STDOUT, "sampled: {$total}\n");
    fwrite(STDOUT, "passed: {$passed}\n");
    fwrite(STDOUT, "failed: {$failed}\n");
    fwrite(STDOUT, "api_exceptions: {$errorCount}\n");
    fwrite(STDOUT, 'pass_rate: ' . ($total > 0 ? number_format(($passed / $total) * 100, 2) : '0.00') . "%\n");

    exit($failed > 0 ? 2 : 0);
} catch (Exception $e) {
    fwrite(STDERR, "[FAIL] {$e->getMessage()}\n");
    exit(1);
}

function buildExpected($phone11, array $row)
{
    $province = isset($row['province']) ? trim((string) $row['province']) : '';
    $city = isset($row['city']) ? trim((string) $row['city']) : '';
    $operatorRaw = isset($row['operator']) ? trim((string) $row['operator']) : '';

    $normalized = normalizeBaseOperatorName($operatorRaw);
    $carrierId = CarrierCatalog::getCarrierIdByName($normalized);
    if ($carrierId === null) {
        $carrierId = 27;
    }

    $operator = $normalized;
    $virtualOperator = '';

    if (CarrierCatalog::isVirtualCarrierId($carrierId)) {
        $virtualOperator = CarrierCatalog::getCarrierNameById($carrierId);
        if ($virtualOperator === null) {
            $virtualOperator = $operatorRaw;
        }

        $baseCarrierId = CarrierCatalog::resolveBaseCarrierIdByPhone($phone11);
        if ($baseCarrierId === null) {
            throw new InvalidArgumentException('Virtual number prefix is not mapped: ' . substr($phone11, 0, 4));
        }

        $baseName = CarrierCatalog::getCarrierNameById($baseCarrierId);
        if ($baseName === null) {
            throw new InvalidArgumentException('Base carrier id is unknown: ' . $baseCarrierId);
        }

        $operator = $baseName;
    }

    return array(
        'province' => $province,
        'city' => $city,
        'operator' => $operator,
        'virtual_operator' => $virtualOperator,
    );
}

function normalizeBaseOperatorName($operator)
{
    $operator = (string) $operator;
    if ($operator === '中国移动') {
        return '移动';
    }
    if ($operator === '中国联通') {
        return '联通';
    }
    if ($operator === '中国电信') {
        return '电信';
    }
    if ($operator === '中国广电') {
        return '广电';
    }

    return $operator;
}
