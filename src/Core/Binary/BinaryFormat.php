<?php

declare(strict_types=1);

namespace Jieeit\Phone\Core\Binary;

/**
 * @desc  二进制数据格式定义
 * @author Jieeit
 * @date 2026/5/16 14:13
 */
class BinaryFormat
{
    // 文件魔数，用于识别 phone 二进制数据文件。
    const MAGIC = "JPHN";
    // 当前二进制格式版本号（V2：城市字典 + 运营商编码）。
    const VERSION = 2;
    // 固定使用小端字节序。
    const ENDIAN = "little";

    // 文件头布局（总长度 36 字节）：
    // 0-3   magic (JPHN)
    // 4-5   version (uint16)
    // 6-7   保留位 (uint16)
    // 8-11  记录总数 (uint32)
    // 12-15 索引区偏移 (uint32)
    // 16-19 索引区长度 (uint32)
    // 20-23 字典区偏移 (uint32)
    // 24-27 字典区长度 (uint32)
    // 28-31 城市字典数量 (uint32)
    // 32-35 保留位 (uint32)
    const HEADER_SIZE = 36;
    // 索引区每条记录固定 10 字节：phone7(4) + city_id(4) + carrier_id(2)。
    const INDEX_ENTRY_SIZE = 10;
}
