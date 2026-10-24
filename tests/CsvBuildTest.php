<?php

declare(strict_types=1);

namespace Jieeit\Phone\Tests;

use Jieeit\Phone\Core\Binary\BinaryBuilder;
use Jieeit\Phone\Core\Binary\BinaryReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CsvBuildTest extends TestCase
{
    public function testCsvKeepsLocationAndBothCarrierNamesOutsidePrefixRules()
    {
        if (!function_exists('iconv')) {
            $this->markTestSkipped('CSV build requires iconv.');
        }

        $csv = tempnam(sys_get_temp_dir(), 'phone-csv-');
        $dat = tempnam(sys_get_temp_dir(), 'phone-dat-');

        try {
            $text = "代码,号段,省区,城市,服务商,区号,邮编,区划代码\n"
                . "138,1380001,广东,深圳,中国移动/易索得移动,0755,518000,440300\n"
                . "170,1704123,河北,唐山,中国联通/迪信通,0315,063000,130200\n"
                . "134,1349000,北京,北京,中国移动卫星电话,010,100000,110100\n"
                . "179,1795116,江苏,徐州,优友互联,0516,221000,320300\n";
            file_put_contents($csv, iconv('UTF-8', 'GB18030', $text));

            $result = (new BinaryBuilder())->buildFromCsv($csv, $dat);
            $this->assertSame(4, $result['total']);
            $this->assertSame(0, $result['skipped']);

            $reader = new BinaryReader($dat);
            $this->assertSame(array(
                'phone' => '1380001', 'province' => '广东', 'city' => '深圳',
                'carrier_id' => 62, 'carrier_name' => '易索得移动', 'base_carrier_id' => 1,
            ), $reader->findByPhone7(1380001));
            $this->assertSame(2, $reader->findByPhone7(1704123)['base_carrier_id']);
            $this->assertSame('迪信通', $reader->findByPhone7(1704123)['carrier_name']);
            $this->assertSame('中国移动卫星电话', $reader->findByPhone7(1349000)['carrier_name']);
            $this->assertSame(1, $reader->findByPhone7(1349000)['base_carrier_id']);
            $this->assertSame(3, $reader->findByPhone7(1795116)['base_carrier_id']);
        } finally {
            unlink($csv);
            unlink($dat);
        }
    }

    public function testUnknownVirtualBaseFailsBuildInsteadOfReturningBrandAsOperator()
    {
        if (!function_exists('iconv')) {
            $this->markTestSkipped('CSV build requires iconv.');
        }

        $csv = tempnam(sys_get_temp_dir(), 'phone-csv-');
        $dat = tempnam(sys_get_temp_dir(), 'phone-dat-');
        try {
            $text = "代码,号段,省区,城市,服务商,区号,邮编,区划代码\n"
                . "179,1799996,北京,北京,优友互联,010,100000,110100\n";
            file_put_contents($csv, iconv('UTF-8', 'GB18030', $text));

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot determine base carrier');
            (new BinaryBuilder())->buildFromCsv($csv, $dat);
        } finally {
            unlink($csv);
            unlink($dat);
        }
    }
}
