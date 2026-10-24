<?php

declare(strict_types=1);

namespace Jieeit\Phone\Tests;

use InvalidArgumentException;
use Jieeit\Phone\Facade\Phone;
use PHPUnit\Framework\TestCase;

class PhoneLookupTest extends TestCase
{
    public function testInvalidPhoneShouldThrowException()
    {
        $this->expectException(InvalidArgumentException::class);
        Phone::find('123');
    }

    public function testLookupShouldReturnExpectedFields()
    {
        $result = Phone::find('13213000000');

        $this->assertArrayHasKey('phone', $result);
        $this->assertArrayHasKey('province', $result);
        $this->assertArrayHasKey('city', $result);
        $this->assertArrayHasKey('operator', $result);
        $this->assertArrayHasKey('virtual_operator', $result);
        $this->assertSame('13213000000', $result['phone']);
    }

    public function testVirtualNumberShouldIncludeVirtualOperator()
    {
        try {
            $result = Phone::find('17041234567');
        } catch (InvalidArgumentException $e) {
            $this->markTestSkipped('Virtual sample not found in dataset: ' . $e->getMessage());
            return;
        }

        $this->assertArrayHasKey('virtual_operator', $result);
        $this->assertNotSame('', $result['virtual_operator']);
        $this->assertContains($result['operator'], array('移动', '联通', '电信', '广电'));
    }

    public function testCsvLocationOverridesOldDatabaseLocation()
    {
        $result = Phone::find('13900000000');
        $this->assertSame('北京', $result['province']);
        $this->assertSame('北京', $result['city']);
    }

    public function testVirtualCarrierUsesBaseProvidedByCsvOutsidePrefixRules()
    {
        $result = Phone::find('13800010000');
        $this->assertSame('移动', $result['operator']);
        $this->assertSame('易索得移动', $result['virtual_operator']);
    }

    public function testStandaloneServiceReturnsBaseCarrier()
    {
        $result = Phone::find('13490000000');
        $this->assertSame('移动', $result['operator']);
        $this->assertSame('', $result['virtual_operator']);

        $result = Phone::find('16800040000');
        $this->assertSame('移动', $result['operator']);
        $this->assertSame('', $result['virtual_operator']);

        $this->assertSame('联通', Phone::find('16802090000')['operator']);
        $this->assertSame('电信', Phone::find('16808330000')['operator']);
    }

    public function testUnqualifiedVirtualCarrierIsAssignedToVerifiedBase()
    {
        $result = Phone::find('17951160000');
        $this->assertSame('电信', $result['operator']);
        $this->assertSame('优友互联', $result['virtual_operator']);
    }
}
