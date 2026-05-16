<?php

namespace Jieeit\Phone\Core\Binary;

use Jieeit\Phone\Core\Carrier\CarrierCatalog;
use InvalidArgumentException;
use RuntimeException;

class BinaryReader
{
    private $fp;
    private $header;
    private $cityDict = array();

    public function __construct($filePath)
    {
        if (!is_string($filePath) || $filePath === '') {
            throw new InvalidArgumentException('Data file path must be a non-empty string.');
        }
        if (!is_file($filePath)) {
            throw new RuntimeException('Data file does not exist: ' . $filePath);
        }

        $this->fp = fopen($filePath, 'rb');
        if ($this->fp === false) {
            throw new RuntimeException('Failed to open data file: ' . $filePath);
        }

        $this->header = $this->readHeader();
        // 启动时一次性加载城市字典，查询时避免重复读文件。
        $this->cityDict = $this->readCityDict();
    }

    public function __destruct()
    {
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }
    }

    public function findByPhone7($phone7)
    {
        // 索引区按 phone7 升序排列，使用二分查找。
        $left = 0;
        $right = $this->header['record_count'] - 1;

        while ($left <= $right) {
            $mid = (int) floor(($left + $right) / 2);
            $entry = $this->readIndexEntry($mid);

            if ($entry['phone'] === $phone7) {
                if (!isset($this->cityDict[$entry['city_id']])) {
                    throw new RuntimeException('City dictionary id out of range: ' . $entry['city_id']);
                }
                $carrierName = CarrierCatalog::getCarrierNameById($entry['carrier_id']);
                if ($carrierName === null) {
                    throw new RuntimeException('Unknown carrier id: ' . $entry['carrier_id']);
                }

                // 先返回底层 carrier 信息，后续由 Service 做“虚拟归类 + 对外字段整理”。
                return array(
                    'phone' => str_pad((string) $phone7, 7, '0', STR_PAD_LEFT),
                    'province' => $this->cityDict[$entry['city_id']]['province'],
                    'city' => $this->cityDict[$entry['city_id']]['city'],
                    'carrier_id' => (int) $entry['carrier_id'],
                    'carrier_name' => $carrierName,
                );
            }

            if ($entry['phone'] < $phone7) {
                $left = $mid + 1;
            } else {
                $right = $mid - 1;
            }
        }

        return null;
    }

    private function readHeader()
    {
        if (fseek($this->fp, 0) !== 0) {
            throw new RuntimeException('Failed to seek data file header.');
        }

        $headerBytes = fread($this->fp, BinaryFormat::HEADER_SIZE);
        if ($headerBytes === false || strlen($headerBytes) !== BinaryFormat::HEADER_SIZE) {
            throw new RuntimeException('Invalid binary file header length.');
        }

        $magic = substr($headerBytes, 0, 4);
        if ($magic !== BinaryFormat::MAGIC) {
            throw new RuntimeException('Invalid binary file magic.');
        }

        $versionArr = unpack('vversion', substr($headerBytes, 4, 2));
        $version = (int) $versionArr['version'];
        if ($version !== BinaryFormat::VERSION) {
            throw new RuntimeException('Unsupported binary file version: ' . $version);
        }

        $values = unpack(
            'Vrecord_count/Vindex_offset/Vindex_length/Vdict_offset/Vdict_length/Vcity_count',
            substr($headerBytes, 8, 24)
        );

        $header = array(
            'record_count' => (int) $values['record_count'],
            'index_offset' => (int) $values['index_offset'],
            'index_length' => (int) $values['index_length'],
            'dict_offset' => (int) $values['dict_offset'],
            'dict_length' => (int) $values['dict_length'],
            'city_count' => (int) $values['city_count'],
        );

        $expectedIndexLength = $header['record_count'] * BinaryFormat::INDEX_ENTRY_SIZE;
        if ($header['index_length'] !== $expectedIndexLength) {
            throw new RuntimeException('Index length mismatch in binary file.');
        }

        return $header;
    }

    private function readCityDict()
    {
        if ($this->header['city_count'] < 0) {
            throw new RuntimeException('Invalid city_count in header.');
        }

        if (fseek($this->fp, $this->header['dict_offset']) !== 0) {
            throw new RuntimeException('Failed to seek city dictionary area.');
        }

        $dictBytes = fread($this->fp, $this->header['dict_length']);
        if ($dictBytes === false || strlen($dictBytes) !== $this->header['dict_length']) {
            throw new RuntimeException('Failed to read city dictionary area.');
        }

        $cursor = 0;
        $dict = array();

        // 按构建阶段写入顺序读取，索引即 city_id。
        for ($i = 0; $i < $this->header['city_count']; $i++) {
            $province = $this->readLenString($dictBytes, $cursor);
            $city = $this->readLenString($dictBytes, $cursor);
            $dict[$i] = array('province' => $province, 'city' => $city);
        }

        if ($cursor !== strlen($dictBytes)) {
            throw new RuntimeException('City dictionary trailing bytes detected.');
        }

        return $dict;
    }

    private function readIndexEntry($index)
    {
        $offset = $this->header['index_offset'] + ($index * BinaryFormat::INDEX_ENTRY_SIZE);
        if (fseek($this->fp, $offset) !== 0) {
            throw new RuntimeException('Failed to seek index entry.');
        }

        $bytes = fread($this->fp, BinaryFormat::INDEX_ENTRY_SIZE);
        if ($bytes === false || strlen($bytes) !== BinaryFormat::INDEX_ENTRY_SIZE) {
            throw new RuntimeException('Failed to read index entry.');
        }

        $phoneCity = unpack('Vphone/Vcity_id', substr($bytes, 0, 8));
        $carrierData = unpack('vcarrier_id', substr($bytes, 8, 2));

        return array(
            'phone' => (int) $phoneCity['phone'],
            'city_id' => (int) $phoneCity['city_id'],
            'carrier_id' => (int) $carrierData['carrier_id'],
        );
    }

    private function readLenString($bytes, &$cursor)
    {
        if (($cursor + 2) > strlen($bytes)) {
            throw new RuntimeException('Corrupted dictionary: invalid string length prefix.');
        }

        $lenArr = unpack('vlen', substr($bytes, $cursor, 2));
        $len = (int) $lenArr['len'];
        $cursor += 2;

        if (($cursor + $len) > strlen($bytes)) {
            throw new RuntimeException('Corrupted dictionary: string data out of bounds.');
        }

        $value = substr($bytes, $cursor, $len);
        $cursor += $len;

        return $value;
    }
}
