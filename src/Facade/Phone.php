<?php

declare(strict_types=1);

namespace Jieeit\Phone\Facade;

use Jieeit\Phone\Service\PhoneLookupService;

/**
 * @desc  Facade 门面类，提供统一静态查询入口
 * @author Jieeit
 * @date 2026/5/16 14:13
 */
class Phone
{
    private static $service;

    /**
     * @param string|int $phone 11位手机号
     * @return array
     */
    public static function find($phone)
    {
        if (!self::$service instanceof PhoneLookupService) {
            self::$service = new PhoneLookupService();
        }

        return self::$service->find($phone);
    }
}
