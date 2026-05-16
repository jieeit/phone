<?php

declare(strict_types=1);

namespace Jieeit\Phone\Contract;

/**
 * @desc  手机号查询接口定义
 * @author Jieeit
 * @date 2026/5/16 14:13
 */
interface PhoneLookupInterface
{
    /**
     * @param string|int $phone 11位手机号
     * @return array
     */
    public function find($phone);
}
