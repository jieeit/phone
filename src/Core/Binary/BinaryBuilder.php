<?php

namespace Jieeit\Phone\Core\Binary;

use Jieeit\Phone\Core\Carrier\CarrierCatalog;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class BinaryBuilder
{
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

        // rows 存索引记录；cityDict 做省市去重字典，减少重复文本占用。
        $rows = array();
        $seen = array();
        $cityDict = array();
        $cityIdMap = array();
        $skipped = 0;

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $phone = isset($row['phone']) ? trim((string) $row['phone']) : '';
            $province = isset($row['province']) ? trim((string) $row['province']) : '';
            $city = isset($row['city']) ? trim((string) $row['city']) : '';
            $operatorRaw = isset($row['operator']) ? trim((string) $row['operator']) : '';

            if (!preg_match('/^[0-9]{7}$/', $phone)) {
                $skipped++;
                continue;
            }
            if (isset($seen[$phone])) {
                $skipped++;
                continue;
            }
            if ($province === '' || $city === '' || $operatorRaw === '') {
                $skipped++;
                continue;
            }

            // 运营商字符串统一转 ID，未知值回退到“虚拟运营商(27)”。
            $carrierId = $this->normalizeCarrierId($operatorRaw);

            $cityKey = $province . '|' . $city;
            if (!isset($cityIdMap[$cityKey])) {
                $cityIdMap[$cityKey] = count($cityDict);
                $cityDict[] = array('province' => $province, 'city' => $city);
            }

            $seen[$phone] = true;
            $rows[] = array(
                'phone' => (int) $phone,
                'city_id' => (int) $cityIdMap[$cityKey],
                'carrier_id' => (int) $carrierId,
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

        // 索引区：phone7 + city_id + carrier_id（定长记录，支持二分查找）。
        $indexBlob = '';
        for ($i = 0; $i < count($rows); $i++) {
            $row = $rows[$i];
            $indexBlob .= pack('VVv', (int) $row['phone'], (int) $row['city_id'], (int) $row['carrier_id']);
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
}
