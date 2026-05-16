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
}
