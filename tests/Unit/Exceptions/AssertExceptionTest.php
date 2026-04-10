<?php

namespace Adsry\Tests\Unit\Exceptions;

use Adsry\Exceptions\AssertException;
use PHPUnit\Framework\TestCase;

class AssertExceptionTest extends TestCase
{
    public function testPassesWhenCorrectType()
    {
        $this->expectNotToPerformAssertions();
        AssertException::assertInstanceOf(new \stdClass(), \stdClass::class);
    }

    public function testThrowsWhenWrongType()
    {
        $this->expectException(AssertException::class);
        $this->expectExceptionMessage(\stdClass::class);
        AssertException::assertInstanceOf(new \DateTime(), \stdClass::class);
    }

    public function testMessageIncludesActualTypeName()
    {
        try {
            AssertException::assertInstanceOf('not-an-object', \stdClass::class);
            $this->fail('Expected AssertException');
        } catch (AssertException $e) {
            $this->assertStringContainsString('string', $e->getMessage());
        }
    }
}
