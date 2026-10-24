#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Jieeit\Phone\Core\Binary\BinaryReader;
use Jieeit\Phone\Core\Carrier\CarrierCatalog;

$options = getopt('', array('csv:', 'data:'));
if (!isset($options['csv'], $options['data'])) {
    fwrite(STDERR, "Usage: php tests/verify_csv_data.php --csv=<source.csv> --data=<phone.dat>\n");
    exit(1);
}

$reader = new BinaryReader($options['data']);
$fp = fopen($options['csv'], 'rb');
if ($fp === false || fgetcsv($fp) === false) {
    fwrite(STDERR, "Cannot read CSV.\n");
    exit(1);
}

$count = 0;
$valueAddedCarrierByPhone = array(
    '1680004' => 1, '1680209' => 2, '1680833' => 3, '1680878' => 3,
    '1681029' => 1, '1681078' => 3, '1681611' => 1, '1681669' => 2,
    '1682333' => 3, '1683136' => 1, '1683267' => 1, '1683645' => 1,
    '1685518' => 1, '1685710' => 1, '1686279' => 1, '1686314' => 3,
    '1686672' => 3, '1687199' => 3, '1687902' => 3, '1688474' => 1,
    '1688610' => 2, '1688779' => 3, '1688838' => 1, '1689472' => 1,
    '1689683' => 3, '1689898' => 3,
);
try {
    while (($fields = fgetcsv($fp)) !== false) {
        $phone = $fields[1];
        $province = iconv('GB18030', 'UTF-8', $fields[2]);
        $city = iconv('GB18030', 'UTF-8', $fields[3]);
        $operator = iconv('GB18030', 'UTF-8', $fields[4]);
        $base = 0;
        $name = $operator;
        if ($operator === '中国联通-虚拟运营商') {
            $base = 2;
            $name = '虚拟运营商';
        } elseif (strpos($operator, '/') !== false) {
            list($baseName, $name) = explode('/', $operator, 2);
            $base = CarrierCatalog::getCarrierIdByName(substr($baseName, strlen('中国')));
            if ($name === '虚拟商') {
                $name = '虚拟运营商';
            }
        } elseif (in_array($operator, array('中国移动', '中国联通', '中国电信', '中国广电'), true)) {
            $name = substr($operator, strlen('中国'));
        }
        if ($operator === '中国移动卫星电话') {
            $base = 1;
        } elseif ($operator === '电信增值业务') {
            if (!isset($valueAddedCarrierByPhone[$phone])) {
                throw new RuntimeException('Missing base carrier for ' . $phone);
            }
            $base = $valueAddedCarrierByPhone[$phone];
        } elseif (in_array($phone, array('1790116', '1790916', '1791016', '1795116'), true)) {
            $base = 3;
        }

        $actual = $reader->findByPhone7((int) $phone);
        if ($actual === null || $actual['province'] !== $province || $actual['city'] !== $city
            || $actual['carrier_name'] !== $name || ($base !== 0 && $actual['base_carrier_id'] !== $base)
            || ($actual['carrier_id'] > 4 && ($actual['base_carrier_id'] < 1 || $actual['base_carrier_id'] > 4))) {
            throw new RuntimeException('CSV mismatch for ' . $phone . ': ' . json_encode($actual, JSON_UNESCAPED_UNICODE));
        }
        $count++;
    }
} finally {
    fclose($fp);
}

echo "Verified {$count} CSV segments against binary file.\n";
