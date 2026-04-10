<?php

namespace Adsry\Tests\Unit\Adapters\Redis;

use Adsry\Adapters\Redis\RedisConsumer;
use Adsry\Adapters\Redis\RedisContext;
use Adsry\Adapters\Redis\RedisDestination;
use Adsry\Adapters\Redis\RedisMessage;
use Adsry\Interfaces\Redis as RedisInterface;
use Adsry\Utils\JsonSerializer;
use PHPUnit\Framework\TestCase;

class RedisConsumerTest extends TestCase
{
    private function makeConsumer($mockRedis, $queueName = 'testq')
    {
        $ctx = $this->getMockBuilder(RedisContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $ctx->method('getRedis')->willReturn($mockRedis);
        $ctx->method('getSerializer')->willReturn(new JsonSerializer());

        return new RedisConsumer($ctx, new RedisDestination($queueName));
    }

    private function makeMessage($body = 'body', $reservedKey = 'raw-payload')
    {
        $m = new RedisMessage($body);
        $m->setReservedKey($reservedKey);
        return $m;
    }

    // --- acknowledge ---

    public function testAcknowledgeCallsZremWithReservedKey()
    {
        $mockRedis = $this->createMock(RedisInterface::class);
        $mockRedis->expects($this->once())
            ->method('zrem')
            ->with('testq:reserved', 'raw-payload');

        $consumer = $this->makeConsumer($mockRedis);
        $consumer->acknowledge($this->makeMessage('body', 'raw-payload'));
    }

    // --- C3: reject(requeue=false) uses only acknowledge, no evalString ---

    public function testRejectWithoutRequeueCallsAcknowledgeOnly()
    {
        $mockRedis = $this->createMock(RedisInterface::class);
        $mockRedis->expects($this->once())->method('zrem');
        $mockRedis->expects($this->never())->method('evalString');
        $mockRedis->expects($this->never())->method('lpush');

        $consumer = $this->makeConsumer($mockRedis);
        $consumer->reject($this->makeMessage(), false);
    }

    // --- C3: reject(requeue=true) is a single atomic evalString, no separate ZREM/LPUSH ---

    public function testRejectWithRequeueCallsEvalStringAtomically()
    {
        $mockRedis = $this->createMock(RedisInterface::class);
        $mockRedis->expects($this->never())->method('zrem');
        $mockRedis->expects($this->never())->method('lpush');
        $mockRedis->expects($this->once())
            ->method('evalString')
            ->with(
                RedisConsumer::atomicAcknowledgeAndRequeue(),
                ['testq:reserved', 'testq'],
                $this->callback(function ($args) {
                    // ARGV[1] = reservedKey, ARGV[2] = new payload
                    return $args[0] === 'raw-payload' && is_string($args[1]);
                })
            );

        $consumer = $this->makeConsumer($mockRedis);
        $consumer->reject($this->makeMessage('body', 'raw-payload'), true);
    }

    // --- C4: requeue payload is built from live message, not reservedKey snapshot ---

    public function testRejectWithRequeueUsesLiveMessageBody()
    {
        $staleSnapshot = json_encode(['body' => 'stale', 'properties' => [], 'headers' => []]);
        $message = new RedisMessage('live body');
        $message->setReservedKey($staleSnapshot);

        $capturedPayload = null;
        $mockRedis = $this->createMock(RedisInterface::class);
        $mockRedis->method('evalString')
            ->willReturnCallback(function ($script, $keys, $args) use (&$capturedPayload) {
                $capturedPayload = $args[1]; // ARGV[2]
            });

        $consumer = $this->makeConsumer($mockRedis);
        $consumer->reject($message, true);

        $data = json_decode($capturedPayload, true);
        $this->assertSame('live body', $data['body'],
            'C4: requeued payload must use the live message body, not the stale snapshot');
    }

    public function testRejectWithRequeuePreservesAttemptsHeaderForRedeliveryDetection()
    {
        // isRedelivered() is a transient PHP property — not serialized to JSON.
        // The queue derives it in processResult() via: getAttempts() > 1.
        // The requeued payload must carry the original attempts count so that
        // the next processResult() call increments it and sets isRedelivered correctly.
        $message = new RedisMessage('body');
        $message->setHeader('attempts', 1); // already processed once
        $message->setReservedKey('raw-reserved');

        $capturedPayload = null;
        $mockRedis = $this->createMock(RedisInterface::class);
        $mockRedis->method('evalString')
            ->willReturnCallback(function ($script, $keys, $args) use (&$capturedPayload) {
                $capturedPayload = $args[1];
            });

        $consumer = $this->makeConsumer($mockRedis);
        $consumer->reject($message, true);

        $serializer = new JsonSerializer();
        $restored = $serializer->toMessage($capturedPayload);
        $this->assertSame(1, $restored->getAttempts(),
            'Attempts header must be preserved so processResult() sets isRedelivered=true on next receive');
    }

    // --- C5: receive() must never write to stdout ---

    public function testReceiveProducesNoOutput()
    {
        $rawPayload = json_encode(['body' => 'msg', 'properties' => [], 'headers' => ['attempts' => 0]]);

        $mockRedis = $this->createMock(RedisInterface::class);
        // Return null for the two migrate calls, then the message on the pop call
        $mockRedis->method('evalString')->willReturnOnConsecutiveCalls(
            null,       // migrateExpired :delayed
            null,       // migrateExpired :reserved
            $rawPayload // atomicPopAndReserve
        );

        $consumer = $this->makeConsumer($mockRedis);

        $this->expectOutputString('');
        $consumer->receive(1000);
    }
}
