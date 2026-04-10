<?php

namespace Adsry\Tests\Unit\Adapters\Redis;

use Adsry\Adapters\Redis\RedisMessage;
use PHPUnit\Framework\TestCase;

class RedisMessageTest extends TestCase
{
    public function testConstructorDefaults()
    {
        $m = new RedisMessage();
        $this->assertSame('', $m->getBody());
        $this->assertSame([], $m->getProperties());
        $this->assertSame([], $m->getHeaders());
        $this->assertFalse($m->isRedelivered());
    }

    public function testBodyGetterSetter()
    {
        $m = new RedisMessage();
        $m->setBody('payload');
        $this->assertSame('payload', $m->getBody());
    }

    public function testRedeliveredDefaultsFalse()
    {
        $m = new RedisMessage('x');
        $this->assertFalse($m->isRedelivered());
        $m->setRedelivered(true);
        $this->assertTrue($m->isRedelivered());
    }

    public function testAttemptsHeaderDefaultsToZero()
    {
        $m = new RedisMessage();
        $this->assertSame(0, $m->getAttempts());
    }

    public function testAttemptsReadsFromHeader()
    {
        $m = new RedisMessage();
        $m->setHeader('attempts', 3);
        $this->assertSame(3, $m->getAttempts());
    }

    public function testReservedKeyGetterSetter()
    {
        $m = new RedisMessage();
        $m->setReservedKey('raw-payload-json');
        $this->assertSame('raw-payload-json', $m->getReservedKey());
    }

    public function testPropertyHelpers()
    {
        $m = new RedisMessage();
        $m->setProperty('foo', 'bar');
        $this->assertSame('bar', $m->getProperty('foo'));
        $this->assertSame('default', $m->getProperty('missing', 'default'));
    }

    public function testMessageIdStoredInHeader()
    {
        $m = new RedisMessage();
        $m->setMessageId('uuid-123');
        $this->assertSame('uuid-123', $m->getMessageId());
        $this->assertArrayHasKey('message_id', $m->getHeaders());
    }
}
