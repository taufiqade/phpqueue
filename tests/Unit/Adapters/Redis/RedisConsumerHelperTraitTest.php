<?php

namespace Adsry\Tests\Unit\Adapters\Redis;

use Adsry\Adapters\Redis\RedisConsumerHelperTrait;
use Adsry\Adapters\Redis\RedisContext;
use Adsry\Adapters\Redis\RedisDestination;
use Adsry\Adapters\Redis\RedisResult;
use Adsry\Interfaces\Redis as RedisInterface;
use Adsry\Utils\JsonSerializer;
use PHPUnit\Framework\TestCase;

/**
 * Concrete class that exposes protected trait methods for testing.
 */
class ConcreteConsumer
{
    use RedisConsumerHelperTrait;

    private $context;

    public function __construct($context)
    {
        $this->context = $context;
    }

    protected function getContext()
    {
        return $this->context;
    }

    public function callProcessResult(RedisResult $result)
    {
        return $this->processResult($result);
    }

    public function callReceiveMessage(array $queues, $timeout, $redeliveryDelay)
    {
        return $this->receiveMessage($queues, $timeout, $redeliveryDelay);
    }
}

class RedisConsumerHelperTraitTest extends TestCase
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

    private function makePayload(array $override = [])
    {
        return json_encode(array_merge(
            ['body' => 'hello', 'properties' => [], 'headers' => ['attempts' => 0]],
            $override
        ));
    }

    // --- C2: processResult uses raw payload as reservedKey ---

    public function testProcessResultSetsReservedKeyToRawPayload()
    {
        $raw = $this->makePayload();
        $mockRedis = $this->createMock(RedisInterface::class);
        $consumer = new ConcreteConsumer($this->makeContext($mockRedis));

        $message = $consumer->callProcessResult(new RedisResult('q', $raw));

        $this->assertNotNull($message);
        $this->assertSame($raw, $message->getReservedKey(),
            'reservedKey must equal the raw payload so acknowledge() ZREMs the correct ZSET member');
    }

    // --- C2: expired messages are ZREMed and discarded, not re-looped ---

    public function testProcessResultZremsExpiredMessageAndReturnsNull()
    {
        $raw = $this->makePayload(['headers' => ['attempts' => 0, 'expires_at' => time() - 10]]);

        $mockRedis = $this->createMock(RedisInterface::class);
        $mockRedis->expects($this->once())
            ->method('zrem')
            ->with('q:reserved', $raw);

        $consumer = new ConcreteConsumer($this->makeContext($mockRedis));
        $result = $consumer->callProcessResult(new RedisResult('q', $raw));

        $this->assertNull($result, 'Expired message must be discarded');
    }

    // --- attempt counter ---

    public function testProcessResultIncrementsAttempts()
    {
        $raw = $this->makePayload(['headers' => ['attempts' => 2]]);
        $mockRedis = $this->createMock(RedisInterface::class);
        $consumer = new ConcreteConsumer($this->makeContext($mockRedis));

        $message = $consumer->callProcessResult(new RedisResult('q', $raw));

        $this->assertSame(3, $message->getAttempts());
    }

    public function testProcessResultSetsRedeliveredOnSecondAttempt()
    {
        $raw = $this->makePayload(['headers' => ['attempts' => 1]]);
        $mockRedis = $this->createMock(RedisInterface::class);
        $consumer = new ConcreteConsumer($this->makeContext($mockRedis));

        $message = $consumer->callProcessResult(new RedisResult('q', $raw));

        $this->assertTrue($message->isRedelivered());
    }

    public function testProcessResultNotRedeliveredOnFirstAttempt()
    {
        $raw = $this->makePayload();
        $mockRedis = $this->createMock(RedisInterface::class);
        $consumer = new ConcreteConsumer($this->makeContext($mockRedis));

        $message = $consumer->callProcessResult(new RedisResult('q', $raw));

        $this->assertFalse($message->isRedelivered());
    }

    // --- Lua scripts are non-empty and contain expected Redis commands ---

    public function testAtomicPopAndReserveScriptContainsRpopAndZadd()
    {
        // Call via the concrete class, not directly on the trait (PHP 8.4+ deprecation)
        $script = ConcreteConsumer::atomicPopAndReserve();
        $this->assertStringContainsString('rpop', $script);
        $this->assertStringContainsString('zadd', $script);
    }

    public function testAtomicAcknowledgeAndRequeueScriptContainsZremAndLpush()
    {
        $script = ConcreteConsumer::atomicAcknowledgeAndRequeue();
        $this->assertStringContainsString('zrem', $script);
        $this->assertStringContainsString('lpush', $script);
    }

    public function testMigrateExpiredScriptContainsZrangebyscoreAndLpush()
    {
        $script = ConcreteConsumer::migrateExpired();
        $this->assertStringContainsString('zrangebyscore', $script);
        $this->assertStringContainsString('lpush', $script);
        $this->assertStringContainsString('zremrangebyrank', $script);
    }

    // --- receiveMessage returns message when Redis has one ---

    public function testReceiveMessageReturnsMessageFromRedis()
    {
        $raw = $this->makePayload(['body' => 'from-redis']);
        $mockRedis = $this->createMock(RedisInterface::class);
        $mockRedis->method('evalString')->willReturnOnConsecutiveCalls(
            null,  // migrateExpired :delayed
            null,  // migrateExpired :reserved
            $raw   // atomicPopAndReserve
        );

        $consumer = new ConcreteConsumer($this->makeContext($mockRedis));
        $message = $consumer->callReceiveMessage([new RedisDestination('q')], 5, 300);

        $this->assertNotNull($message);
        $this->assertSame('from-redis', $message->getBody());
    }
}
