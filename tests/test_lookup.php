<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Jieeit\Phone\Facade\Phone;

function assertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    $phone = isset($argv[1]) ? (string) $argv[1] : '13213001234';
    $result = Phone::find($phone);

    assertTrue(is_array($result), 'result must be array');
    assertTrue(isset($result['phone']), 'phone key missing');
    assertTrue(isset($result['province']), 'province key missing');
    assertTrue(isset($result['city']), 'city key missing');
    assertTrue(isset($result['operator']), 'operator key missing');
    assertTrue(isset($result['virtual_operator']), 'virtual_operator key missing');
    assertTrue($result['phone'] === $phone, 'phone in result must equal input phone');

    echo "[PASS] lookup success\n";
    echo 'phone: ' . $result['phone'] . "\n";
    echo 'province: ' . $result['province'] . "\n";
    echo 'city: ' . $result['city'] . "\n";
    echo 'operator: ' . $result['operator'] . "\n";

    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
