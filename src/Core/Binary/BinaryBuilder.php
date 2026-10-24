<?php

declare(strict_types=1);

namespace Jieeit\Phone\Core\Binary;

use Jieeit\Phone\Core\Carrier\CarrierCatalog;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * @desc  二进制手机号数据构建器
 * @author Jieeit
 * @date 2026/5/16 14:13
 */
class BinaryBuilder
{
    // CSV 仅写“电信增值业务”的 26 个号段，在旧库 huafeihub.sa_phone_number_segment
    // 中有各自的基础运营商（移动/联通/电信），不可仅根据“电信”二字归类。
    private static $valueAddedCarrierByPhone = array(
        '1680004' => 1, '1680209' => 2, '1680833' => 3, '1680878' => 3,
        '1681029' => 1, '1681078' => 3, '1681611' => 1, '1681669' => 2,
        '1682333' => 3, '1683136' => 1, '1683267' => 1, '1683645' => 1,
        '1685518' => 1, '1685710' => 1, '1686279' => 1, '1686314' => 3,
        '1686672' => 3, '1687199' => 3, '1687902' => 3, '1688474' => 1,
        '1688610' => 2, '1688779' => 3, '1688838' => 1, '1689472' => 1,
        '1689683' => 3, '1689898' => 3,
    );

    public function buildFromPdo(PDO $pdo, $tableName, $outputFile)
    {
        if (!is_string($tableName) || $tableName === '') {
            throw new InvalidArgumentException('Table name must be a non-empty string.');
        }
        if (!is_string($outputFile) || $outputFile === '') {
            throw new InvalidArgumentException('Output file must be a non-empty string.');
        }

        $sql = sprintf(
            'SELECT phone, province, city, operator FROM `%s` ORDER BY phone ASC',
            str_replace('`', '', $tableName)
        );

        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to query source table.');
        }

        $records = (function () use ($stmt) {
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                yield $row;
            }
        })();

        return $this->buildFromRows($records, $outputFile, false);
    }

    public function buildFromCsv($csvFile, $outputFile)
    {
        if (!is_file($csvFile)) {
            throw new InvalidArgumentException('CSV file does not exist: ' . $csvFile);
        }
        if (!function_exists('iconv')) {
            throw new RuntimeException('The iconv extension is required to read GB18030 CSV data.');
        }

        $records = (function () use ($csvFile) {
            $fp = fopen($csvFile, 'rb');
            if ($fp === false) {
                throw new RuntimeException('Failed to open CSV file: ' . $csvFile);
            }
            try {
                $header = fgetcsv($fp);
                if ($header === false || count($header) !== 8 || $this->decodeCsvField($header[1]) !== '号段'
                    || $this->decodeCsvField($header[2]) !== '省区'
                    || $this->decodeCsvField($header[3]) !== '城市'
                    || $this->decodeCsvField($header[4]) !== '服务商') {
                    throw new RuntimeException('Unexpected CSV header.');
                }
                $line = 1;
                while (($fields = fgetcsv($fp)) !== false) {
                    $line++;
                    if (count($fields) !== 8) {
                        throw new RuntimeException('Invalid CSV columns at line ' . $line);
                    }
                    yield array(
                        'phone' => $this->decodeCsvField($fields[1]),
                        'province' => $this->decodeCsvField($fields[2]),
                        'city' => $this->decodeCsvField($fields[3]),
                        'operator' => $this->decodeCsvField($fields[4]),
                    );
                }
            } finally {
                fclose($fp);
            }
        })();

        return $this->buildFromRows($records, $outputFile, true);
    }

    private function decodeCsvField($field)
    {
        $value = iconv('GB18030', 'UTF-8', $field);
        if ($value === false) {
            throw new RuntimeException('Invalid GB18030 CSV text.');
        }
        return $value;
    }

    private function buildFromRows($records, $outputFile, $strict)
    {
        if (!is_string($outputFile) || $outputFile === '') {
            throw new InvalidArgumentException('Output file must be a non-empty string.');
        }

        // rows 存索引记录；cityDict 做省市去重字典，减少重复文本占用。
        $rows = array();
        $seen = array();
        $cityDict = array();
        $cityIdMap = array();
        $skipped = 0;

        foreach ($records as $row) {
            $phone = isset($row['phone']) ? trim((string) $row['phone']) : '';
            $province = isset($row['province']) ? trim((string) $row['province']) : '';
            $city = isset($row['city']) ? trim((string) $row['city']) : '';
            $operatorRaw = isset($row['operator']) ? trim((string) $row['operator']) : '';

            if (!preg_match('/^[0-9]{7}$/', $phone) || isset($seen[$phone])
                || $province === '' || $city === '' || $operatorRaw === '') {
                if ($strict) {
                    throw new RuntimeException('Invalid or duplicate CSV phone segment: ' . $phone);
                }
                $skipped++;
                continue;
            }

            $carrier = $this->parseCarrier($operatorRaw, $phone, $strict);

            $cityKey = $province . '|' . $city;
            if (!isset($cityIdMap[$cityKey])) {
                $cityIdMap[$cityKey] = count($cityDict);
                $cityDict[] = array('province' => $province, 'city' => $city);
            }

            $seen[$phone] = true;
            $rows[] = array(
                'phone' => (int) $phone,
                'city_id' => (int) $cityIdMap[$cityKey],
                'carrier_id' => $carrier['carrier_id'],
                'base_carrier_id' => $carrier['base_carrier_id'],
            );
        }

        usort($rows, function ($a, $b) {
            if ($a['phone'] === $b['phone']) {
                return 0;
            }
            return ($a['phone'] < $b['phone']) ? -1 : 1;
        });

        $this->writeBinary($rows, $cityDict, $outputFile);

        return array(
            'total' => count($rows),
            'skipped' => $skipped,
            'city_dict_count' => count($cityDict),
            'output' => $outputFile,
            'size' => filesize($outputFile),
        );
    }

    private function writeBinary(array $rows, array $cityDict, $outputFile)
    {
        $dir = dirname($outputFile);
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            throw new RuntimeException('Failed to create output directory: ' . $dir);
        }

        // 索引区：phone7 + city_id + carrier_id + base_carrier_id。
        $indexBlob = '';
        for ($i = 0; $i < count($rows); $i++) {
            $row = $rows[$i];
            $indexBlob .= pack('VVvv', (int) $row['phone'], (int) $row['city_id'],
                (int) $row['carrier_id'], (int) $row['base_carrier_id']);
        }

        // 字典区：仅存唯一的省市文本，查询时通过 city_id 回查。
        $dictBlob = $this->packCityDict($cityDict);

        $recordCount = count($rows);
        $indexOffset = BinaryFormat::HEADER_SIZE;
        $indexLength = strlen($indexBlob);
        $dictOffset = $indexOffset + $indexLength;
        $dictLength = strlen($dictBlob);
        $cityCount = count($cityDict);

        $header = BinaryFormat::MAGIC;
        $header .= pack('v', BinaryFormat::VERSION);
        $header .= pack('v', 0);
        $header .= pack('V', $recordCount);
        $header .= pack('V', $indexOffset);
        $header .= pack('V', $indexLength);
        $header .= pack('V', $dictOffset);
        $header .= pack('V', $dictLength);
        $header .= pack('V', $cityCount);
        $header .= pack('V', 0);

        if (strlen($header) !== BinaryFormat::HEADER_SIZE) {
            throw new RuntimeException('Invalid header size generated.');
        }

        $payload = $header . $indexBlob . $dictBlob;
        if (file_put_contents($outputFile, $payload) === false) {
            throw new RuntimeException('Failed to write binary file: ' . $outputFile);
        }
    }

    private function packCityDict(array $cityDict)
    {
        $blob = '';
        for ($i = 0; $i < count($cityDict); $i++) {
            $province = (string) $cityDict[$i]['province'];
            $city = (string) $cityDict[$i]['city'];

            $pLen = strlen($province);
            $cLen = strlen($city);
            if ($pLen > 65535 || $cLen > 65535) {
                throw new RuntimeException('Province or city exceeds 65535 bytes.');
            }

            $blob .= pack('v', $pLen) . $province;
            $blob .= pack('v', $cLen) . $city;
        }

        return $blob;
    }

    private function normalizeCarrierId($operator)
    {
        $normalized = (string) $operator;
        if ($normalized === '中国移动') {
            $normalized = '移动';
        } elseif ($normalized === '中国联通') {
            $normalized = '联通';
        } elseif ($normalized === '中国电信') {
            $normalized = '电信';
        } elseif ($normalized === '中国广电') {
            $normalized = '广电';
        }

        $carrierId = CarrierCatalog::getCarrierIdByName($normalized);
        if ($carrierId !== null) {
            return $carrierId;
        }

        return 27;
    }

    private function parseCarrier($operator, $phone, $strict)
    {
        $baseId = 0;
        $name = $operator;
        if ($operator === '中国联通-虚拟运营商') {
            $name = '虚拟运营商';
            $baseId = 2;
        } elseif (strpos($operator, '/') !== false) {
            list($base, $brand) = explode('/', $operator, 2);
            $baseId = CarrierCatalog::getCarrierIdByName(str_replace('中国', '', $base));
            $name = $brand === '虚拟商' ? '虚拟运营商' : $brand;
            if ($baseId === null || $baseId < 1 || $baseId > 4) {
                throw new RuntimeException('Unknown base carrier for ' . $phone . ': ' . $operator);
            }
        }

        $carrierId = $this->normalizeCarrierId($name);
        if ($strict && $carrierId === 27 && $name !== '虚拟运营商') {
            throw new RuntimeException('Unknown carrier for ' . $phone . ': ' . $operator);
        }
        if ($baseId === 0 && CarrierCatalog::isVirtualCarrierId($carrierId)) {
            $resolved = CarrierCatalog::resolveBaseCarrierIdByPhone($phone);
            $baseId = $resolved === null ? 0 : $resolved;
        }
        if ($carrierId === 60) {
            $baseId = 1; // 中国移动卫星电话
        } elseif ($carrierId === 61) {
            $baseId = isset(self::$valueAddedCarrierByPhone[$phone])
                ? self::$valueAddedCarrierByPhone[$phone] : 0;
        }

        // 源 CSV 的 179xx16 四个品牌没有显式基础运营商；同组 179xx14/15/17
        // 均标为“中国电信/虚拟商”。仅对这些已核实的号段补充归属。
        $telecomVirtualSegments = array('1790116', '1790916', '1791016', '1795116');
        if ($baseId === 0 && in_array($phone, $telecomVirtualSegments, true)) {
            $baseId = 3;
        }
        if ($carrierId > 4 && $baseId === 0) {
            throw new RuntimeException('Cannot determine base carrier for ' . $phone . ': ' . $operator);
        }

        return array('carrier_id' => $carrierId, 'base_carrier_id' => $baseId);
    }
}
