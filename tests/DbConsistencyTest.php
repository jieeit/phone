<?php

declare(strict_types=1);

namespace Jieeit\Phone\Tests;

use InvalidArgumentException;
use Jieeit\Phone\Core\Carrier\CarrierCatalog;
use Jieeit\Phone\Facade\Phone;
use PHPUnit\Framework\TestCase;

class DbConsistencyTest extends TestCase
{
    public function testRandom1000RowsShouldMatchApiResult()
    {
        if (getenv('RUN_DB_CONSISTENCY_TEST') !== '1') {
            $this->markTestSkipped('Set RUN_DB_CONSISTENCY_TEST=1 to enable DB consistency test.');
            return;
        }

        $host = getenv('DB_HOST') ? getenv('DB_HOST') : '127.0.0.1';
        $port = getenv('DB_PORT') ? getenv('DB_PORT') : '3306';
        $user = getenv('DB_USER') ? getenv('DB_USER') : 'root';
        $password = getenv('DB_PASSWORD') ? getenv('DB_PASSWORD') : 'root';
        $database = getenv('DB_DATABASE') ? getenv('DB_DATABASE') : 'test';
        $table = getenv('DB_TABLE') ? getenv('DB_TABLE') : 'sa_phone_number_segment';
        $limit = getenv('DB_SAMPLE_LIMIT') ? (int) getenv('DB_SAMPLE_LIMIT') : 1000;

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
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

        $this->assertNotEmpty($rows, 'No rows returned from DB sample query.');

        foreach ($rows as $row) {
            $phone7 = isset($row['phone']) ? trim((string) $row['phone']) : '';
            if (!preg_match('/^[0-9]{7}$/', $phone7)) {
                $this->fail('Invalid phone7 in DB row: ' . $phone7);
            }

            $phone11 = $phone7 . '0000';
            $expected = $this->buildExpected($phone11, $row);
            $actual = Phone::find($phone11);

            $this->assertSame($expected['province'], $actual['province'], 'province mismatch for ' . $phone11);
            $this->assertSame($expected['city'], $actual['city'], 'city mismatch for ' . $phone11);
            $this->assertSame($expected['operator'], $actual['operator'], 'operator mismatch for ' . $phone11);
            $this->assertSame($expected['virtual_operator'], $actual['virtual_operator'], 'virtual_operator mismatch for ' . $phone11);
        }
    }

    private function buildExpected($phone11, array $row)
    {
        $province = isset($row['province']) ? trim((string) $row['province']) : '';
        $city = isset($row['city']) ? trim((string) $row['city']) : '';
        $operatorRaw = isset($row['operator']) ? trim((string) $row['operator']) : '';

        $normalized = $this->normalizeBaseOperatorName($operatorRaw);
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

    private function normalizeBaseOperatorName($operator)
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
}
