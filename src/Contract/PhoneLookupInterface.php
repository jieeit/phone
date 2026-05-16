<?php

namespace Jieeit\Phone\Contract;

interface PhoneLookupInterface
{
    /**
     * @param string|int $phone 11位手机号
     * @return array
     */
    public function find($phone);
}
