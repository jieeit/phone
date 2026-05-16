<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Jieeit\Phone\Facade\Phone;

// 用法：
// php tests/benchmark.php [phone] [loops]
// 例如：php tests/benchmark.php 13213001234 200000

$phone = isset($argv[1]) ? (string) $argv[1] : '13213001234';
$loops = isset($argv[2]) ? (int) $argv[2] : 100000;

if (!preg_match('/^[0-9]{11}$/', $phone)) {
    fwrite(STDERR, "Invalid phone, must be 11 digits.\n");
    exit(1);
}

if ($loops <= 0) {
    fwrite(STDERR, "Invalid loops, must be > 0.\n");
    exit(1);
}

try {
    // 预热，避免首次初始化影响压测结果。
    Phone::find($phone);

    $start = microtime(true);
    for ($i = 0; $i < $loops; $i++) {
        Phone::find($phone);
    }
    $cost = microtime(true) - $start;

    $qps = ($cost > 0) ? ($loops / $cost) : 0;
    $avgUs = ($loops > 0) ? (($cost * 1000000) / $loops) : 0;

    echo "Benchmark done\n";
    echo 'phone: ' . $phone . "\n";
    echo 'loops: ' . $loops . "\n";
    echo 'total_time_sec: ' . number_format($cost, 6) . "\n";
    echo 'qps: ' . number_format($qps, 2) . "\n";
    echo 'avg_us_per_query: ' . number_format($avgUs, 2) . "\n";

    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
