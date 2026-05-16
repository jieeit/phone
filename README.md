# 手机号码归属地查询

[![Latest Stable Version](http://poser.pugx.org/jieeit/phone/v)](https://packagist.org/packages/jieeit/phone)
[![Total Downloads](http://poser.pugx.org/jieeit/phone/downloads)](https://packagist.org/packages/jieeit/phone)
[![Jieeit Phone](https://img.shields.io/github/v/release/jieeit/phone?include_prereleases)]()
[![Jieeit Phone](https://img.shields.io/badge/build-passing-brightgreen.svg)]()
[![Jieeit Phone](https://img.shields.io/github/last-commit/jieeit/phone/main)]()
[![Jieeit Phone](https://img.shields.io/github/v/tag/jieeit/phone?color=ff69b4)]()
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg)](https://php.net/)


可能是目前最全的手机号归属地查询库，**支持虚拟运营商与物联卡**。

- 数据截止时间: 2026年3月
- 手机号段记录条数：**520,170**
- 基于 PHP 实现，采用**二分查找法**，查询高效
- 归属地数据文件大小：**5,207,793 字节**

---

## 安装

```bash
composer require jieeit/phone
```

---

## 使用

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Jieeit\Phone\Facade\Phone;

$info = Phone::find('13213000000');
print_r($info);
```

**输出示例：**

```php
Array
(
    [phone] => 13213000000      // 查询号码
    [province] => 河南          // 省份
    [city] => 郑州              // 城市
    [operator] => 联通          // 归属基础运营商
    [virtual_operator] =>       // 虚拟运营商名称（如有）
)
```

---

## 虚拟运营商处理规则

虚拟运营商会自动归类到四大基础运营商（移动 / 联通 / 电信 / 广电），并额外返回原始运营商名称。

| 字段 | 说明 |
|------|------|
| `operator` | 归类后的基础运营商 |
| `virtual_operator` | 原始虚拟运营商名称（如 `迪信通`） |

**支持的虚拟运营商列表（部分）：**

铁通、天音移动、小米移动、星美通讯、星美通信、长江时代、海航通信、国美极信、民生通讯、朗玛移动、迪信通、京东通信、分享在线、话机通信、话机世界、用友通信、三五互联、联想懂的、连连科技、丰信通信、乐语通信、爱施德、虚拟运营商、中兴视通、中邮世纪、世纪互联、银盛通信、中邮普泰、华翔联信、苏宁互联、鹏博士移动、国美通信、优友互联、博元讯息、阿里通信、中期移动、远特信时空、广东恒大、红豆集团、星美生活、网信移动、分享通信、无限互联、日日顺通信、北纬通信、远特通信、长城移动、中麦通信、巴士在线、普泰移动、蜗牛移动、全民优打、豆电信、优酷移动、华云互联

---

## phone.dat 文件格式

```
┌───────────────┬───────────────┬────────────────────────────────┐
│ 偏移（字节）   │ 大小（字节）   │ 说明                           │
├───────────────┼───────────────┼────────────────────────────────┤
│ 0             │ 4             │ 文件魔数（JPHN）                │
│ 4             │ 2             │ 版本号（当前为 2）              │
│ 6             │ 2             │ 保留字段                        │
│ 8             │ 4             │ 记录总数                        │
│ 12            │ 4             │ 索引区偏移                      │
│ 16            │ 4             │ 索引区长度                      │
│ 20            │ 4             │ 字典区偏移                      │
│ 24            │ 4             │ 字典区长度                      │
│ 28            │ 4             │ 城市字典数量                    │
│ 32            │ 4             │ 保留字段                        │
├───────────────┼───────────────┼────────────────────────────────┤
│ 36            │ 变长          │ 索引区（每条固定 10 字节）       │
│               │               │ └─ 手机号前7位(uint32)          │
│               │               │ └─ city_id(uint32)              │
│               │               │ └─ carrier_id(uint16)           │
├───────────────┼───────────────┼────────────────────────────────┤
│ 变长          │ 变长          │ 字典区（省份/城市文本）          │
│               │               │ └─ uint16 长度前缀 + 文本数据    │
└───────────────┴───────────────┴────────────────────────────────┘
```

- **头部总长度**：36 字节
- **编码方式**：字典区顺序存储，使用 `uint16` 长度前缀编码

---

## 开源协议

MIT License，详见项目根目录 `LICENSE` 文件。