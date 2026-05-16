<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Jieeit\Phone\Facade\Phone;

function fail($message)
{
    fwrite(STDERR, '[FAIL] ' . $message . "\n");
    exit(1);
}

function pass($message)
{
    fwrite(STDOUT, '[PASS] ' . $message . "\n");
}

// 用法：
// php tests/test_virtual_lookup.php <phone> <expected_base_operator> <expected_virtual_operator>
// 示例：
// php tests/test_virtual_lookup.php 17041234567 联通 迪信通

$phone = isset($argv[1]) ? (string) $argv[1] : '17041234567';
$expectedBase = isset($argv[2]) ? (string) $argv[2] : '联通';
$expectedVirtual = isset($argv[3]) ? (string) $argv[3] : null;

if (!preg_match('/^[0-9]{11}$/', $phone)) {
    fail('phone must be exactly 11 digits');
}

try {
    $result = Phone::find($phone);

    if (!is_array($result)) {
        fail('result must be array');
    }

    if (!isset($result['operator'])) {
        fail('operator field missing');
    }

    if (!isset($result['virtual_operator'])) {
        fail('virtual_operator field missing');
    }

    if ($result['operator'] !== $expectedBase) {
        fail('base operator mismatch, expected: ' . $expectedBase . ', actual: ' . $result['operator']);
    }

    if ($expectedVirtual !== null && $expectedVirtual !== '') {
        if ($result['virtual_operator'] !== $expectedVirtual) {
            fail('virtual operator mismatch, expected: ' . $expectedVirtual . ', actual: ' . $result['virtual_operator']);
        }
    } else {
        if ($result['virtual_operator'] === '') {
            fail('virtual_operator should not be empty for virtual number');
        }
    }

    pass('virtual lookup success');
    fwrite(STDOUT, 'phone: ' . $result['phone'] . "\n");
    fwrite(STDOUT, 'province: ' . $result['province'] . "\n");
    fwrite(STDOUT, 'city: ' . $result['city'] . "\n");
    fwrite(STDOUT, 'operator(base): ' . $result['operator'] . "\n");
    fwrite(STDOUT, 'virtual_operator(raw): ' . $result['virtual_operator'] . "\n");

    exit(0);
} catch (Exception $e) {
    fail($e->getMessage());
}
