<?php

namespace Adsry\Tests\Unit\Adapters\Redis;

use Adsry\Adapters\Redis\RedisContext;
use Adsry\Adapters\Redis\RedisDestination;
use Adsry\Adapters\Redis\RedisMessage;
use Adsry\Adapters\Redis\RedisProducer;
use Adsry\Exceptions\AssertException;
use Adsry\Interfaces\Redis as RedisInterface;
use Adsry\Utils\JsonSerializer;
use PHPUnit\Framework\TestCase;

class RedisProducerTest extends TestCase
{
    private function makeContext($mockRedis)
    {
        $ctx = $this->getMockBuilder(RedisContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $ctx->method('getRedis')->willReturn($mockRedis);
        $ctx->method('getSerializer')->willReturn(new JsonSerializer());
        return $ctx;
    }

    public function testSendCallsLpushForImmediateDelivery()
    {
        $mockRedis = $this->createMock(RedisInterface::class);
        $mockRedis->expects($this->once())
            ->method('lpush')
            ->with('myqueue', $this->isType('string'));

        $producer = new RedisProducer($this->makeContext($mockRedis));
        $producer->send(new RedisDestination('myqueue'), new RedisMessage('body'));
    }

    public function testSendCallsZaddForDelayedDelivery()
    {
        $mockRedis = $this->createMock(RedisInterface::class);
        $mockRedis->expects($this->once())
            ->method('zadd')
            ->with('myqueue:delayed', $this->isType('string'), $this->isType('int'));
        $mockRedis->expects($this->never())->method('lpush');

        $message = new RedisMessage('body');
        $message->setDeliveryDelay(5000); // 5 seconds in ms

        $producer = new RedisProducer($this->makeContext($mockRedis));
        $producer->send(new RedisDestination('myqueue'), $message);
    }

    public function testSendAssignsMessageId()
    {
        $mockRedis = $this->createMock(RedisInterface::class);
        $mockRedis->method('lpush');

        $message = new RedisMessage('body');
        $this->assertNull($message->getMessageId());

        $producer = new RedisProducer($this->makeContext($mockRedis));
        $producer->send(new RedisDestination('myqueue'), $message);

        $this->assertNotNull($message->getMessageId());
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $message->getMessageId(),
            'Message ID should be a v4 UUID'
        );
    }

    public function testSendThrowsIfWrongDestinationType()
    {
        $mockRedis = $this->createMock(RedisInterface::class);
        $producer = new RedisProducer($this->makeContext($mockRedis));

        $this->expectException(AssertException::class);
        $destination = $this->createMock(\Adsry\Interfaces\Destination::class);
        $producer->send($destination, new RedisMessage('body'));
    }

    public function testSendThrowsIfWrongMessageType()
    {
        $mockRedis = $this->createMock(RedisInterface::class);
        $producer = new RedisProducer($this->makeContext($mockRedis));

        $this->expectException(AssertException::class);
        $message = $this->createMock(\Adsry\Interfaces\Message::class);
        $producer->send(new RedisDestination('myqueue'), $message);
    }

    public function testProducerDeliveryDelayFluentInterface()
    {
        $mockRedis = $this->createMock(RedisInterface::class);
        $producer = new RedisProducer($this->makeContext($mockRedis));

        $result = $producer->setDeliveryDelay(3000);
        $this->assertSame($producer, $result);
        $this->assertSame(3000, $producer->getDeliveryDelay());
    }
}
