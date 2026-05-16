<?php

namespace Jieeit\Phone\Core\Carrier;

class CarrierCatalog
{
    // 运营商ID字典：用于构建阶段压缩存储，以及查询阶段反查名称。
    private static $carrier = array(
        1 => '移动',
        2 => '联通',
        3 => '电信',
        4 => '广电',
        5 => '铁通',
        6 => '天音移动',
        7 => '小米移动',
        8 => '星美通讯',
        9 => '星美通信',
        10 => '长江时代',
        11 => '海航通信',
        12 => '国美极信',
        13 => '民生通讯',
        14 => '朗玛移动',
        15 => '迪信通',
        16 => '京东通信',
        17 => '分享在线',
        18 => '话机通信',
        19 => '话机世界',
        20 => '用友通信',
        21 => '三五互联',
        22 => '联想懂的',
        23 => '连连科技',
        24 => '丰信通信',
        25 => '乐语通信',
        26 => '爱施德',
        27 => '虚拟运营商',
        28 => '中兴视通',
        29 => '中邮世纪',
        30 => '世纪互联',
        31 => '银盛通信',
        32 => '中邮普泰',
        33 => '华翔联信',
        34 => '苏宁互联',
        35 => '鹏博士移动',
        36 => '国美通信',
        37 => '优友互联',
        38 => '博元讯息',
        39 => '阿里通信',
        40 => '中期移动',
        41 => '远特信时空',
        42 => '广东恒大',
        43 => '红豆集团',
        44 => '星美生活',
        45 => '网信移动',
        46 => '分享通信',
        47 => '无限互联',
        48 => '日日顺通信',
        49 => '北纬通信',
        50 => '远特通信',
        51 => '长城移动',
        52 => '中麦通信',
        53 => '巴士在线',
        54 => '普泰移动',
        55 => '蜗牛移动',
        56 => '全民优打',
        57 => '豆电信',
        58 => '优酷移动',
        59 => '华云互联',
    );

    private static $virtualPrefixToBase = array(
        '165' => 1,
        '1703' => 1,
        '1705' => 1,
        '1706' => 1,
        '167' => 2,
        '1704' => 2,
        '1707' => 2,
        '1708' => 2,
        '1709' => 2,
        '1710' => 2,
        '1711' => 2,
        '1712' => 2,
        '1713' => 2,
        '1714' => 2,
        '1715' => 2,
        '1716' => 2,
        '1717' => 2,
        '1718' => 2,
        '1719' => 2,
        '162' => 3,
        '1700' => 3,
        '1701' => 3,
        '1702' => 3,
    );

    public static function getCarrierNameById($id)
    {
        return isset(self::$carrier[$id]) ? self::$carrier[$id] : null;
    }

    public static function getCarrierIdByName($name)
    {
        foreach (self::$carrier as $id => $carrierName) {
            if ($carrierName === $name) {
                return (int) $id;
            }
        }

        return null;
    }

    public static function isVirtualCarrierId($id)
    {
        return $id >= 5;
    }

    public static function resolveBaseCarrierIdByPhone($phone)
    {
        $phone = (string) $phone;

        // 先匹配4位前缀（如170x、171x），再回退到3位前缀（如162/165/167）。
        $p4 = substr($phone, 0, 4);
        if (isset(self::$virtualPrefixToBase[$p4])) {
            return (int) self::$virtualPrefixToBase[$p4];
        }

        $p3 = substr($phone, 0, 3);
        if (isset(self::$virtualPrefixToBase[$p3])) {
            return (int) self::$virtualPrefixToBase[$p3];
        }

        return null;
    }
}
