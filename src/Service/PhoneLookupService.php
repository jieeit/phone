<?php

namespace Jieeit\Phone\Service;

use InvalidArgumentException;
use Jieeit\Phone\Contract\PhoneLookupInterface;
use Jieeit\Phone\Core\Carrier\CarrierCatalog;
use Jieeit\Phone\Core\Binary\BinaryReader;

class PhoneLookupService implements PhoneLookupInterface
{
    private $reader;

    public function __construct()
    {
        $dataFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'phone.dat';
        $this->reader = new BinaryReader($dataFile);
    }

    public function find($phone)
    {
        if (!is_string($phone) && !is_int($phone)) {
            throw new InvalidArgumentException('Phone must be a string or integer.');
        }

        $phone = trim((string) $phone);
        if (!preg_match('/^[0-9]{11}$/', $phone)) {
            throw new InvalidArgumentException('Phone must be exactly 11 digits.');
        }

        $phone7 = substr($phone, 0, 7);
        $result = $this->reader->findByPhone7((int) $phone7);

        if ($result === null) {
            throw new InvalidArgumentException('Phone segment not found: ' . $phone7);
        }

        // Reader 返回底层 carrier 信息，Service 在这里做业务语义转换。
        $carrierId = (int) $result['carrier_id'];
        $carrierName = (string) $result['carrier_name'];
        $virtualOperator = '';

        if (CarrierCatalog::isVirtualCarrierId($carrierId)) {
            // 虚拟运营商：对外 operator 返回四大基础运营商，
            // 同时额外返回 virtual_operator 原始品牌名。
            $virtualOperator = $carrierName;
            $baseCarrierId = CarrierCatalog::resolveBaseCarrierIdByPhone($phone);
            if ($baseCarrierId === null) {
                throw new InvalidArgumentException('Virtual number prefix is not mapped to base carrier: ' . substr($phone, 0, 4));
            }

            $baseCarrierName = CarrierCatalog::getCarrierNameById($baseCarrierId);
            if ($baseCarrierName === null) {
                throw new InvalidArgumentException('Base carrier mapping failed for phone: ' . $phone);
            }

            $result['operator'] = $baseCarrierName;
            $result['virtual_operator'] = $virtualOperator;
        } else {
            $result['operator'] = $carrierName;
            $result['virtual_operator'] = '';
        }

        unset($result['carrier_id']);
        unset($result['carrier_name']);
        $result['phone'] = $phone;

        return $result;
    }

}
