<?php

namespace Adsry\Tests\Unit;

use Adsry\QueueWrapper;
use PHPUnit\Framework\TestCase;

class QueueWrapperTest extends TestCase
{
    /** C1: unknown transporter name must throw with the name in the message */
    public function testUnknownTransporterThrowsExceptionContainingName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('nonexistent');

        new QueueWrapper('nonexistent', []);
    }

    /** C1: AVAILABLE_TRANSPORTER must contain the 'redis' key */
    public function testRedisTransporterIsRegistered()
    {
        $this->assertArrayHasKey('redis', QueueWrapper::AVAILABLE_TRANSPORTER);
    }
}
