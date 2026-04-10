<?php

namespace Adsry\Adapters\Redis;

use Adsry\Interfaces\Message;

trait RedisConsumerHelperTrait
{
    /**
     * @var string[]
     */
    protected $queueNames;

    abstract protected function getContext();

    /**
     * Poll interval between RPOP attempts when the queue is empty (microseconds).
     */
    const POLL_INTERVAL_US = 250000; // 250 ms

    /**
     * @param RedisDestination[] $queues
     * @param int                $timeout         in seconds
     * @param int                $redeliveryDelay in seconds
     *
     * @return RedisMessage|null
     */
    protected function receiveMessage(array $queues, $timeout, $redeliveryDelay)
    {
        if (null === $this->queueNames) {
            $this->queueNames = [];
            foreach ($queues as $queue) {
                $this->queueNames[] = $queue->getName();
            }
        }

        $startAt = time();

        while (true) {
            $this->migrateExpiredMessages($this->queueNames);

            foreach ($this->queueNames as $queueName) {
                $redeliveryAt = time() + $redeliveryDelay;
                $raw = $this->getContext()->getRedis()->evalString(
                    self::atomicPopAndReserve(),
                    [$queueName, $queueName . ':reserved'],
                    [$redeliveryAt]
                );

                if ($raw !== null && $raw !== false) {
                    $this->pushQueueNameBack($queueName);
                    if ($message = $this->processResult(new RedisResult($queueName, $raw))) {
                        return $message;
                    }
                }
            }

            if (time() - $startAt >= $timeout) {
                return null;
            }

            usleep(self::POLL_INTERVAL_US);
        }
    }

    /**
     * @param  RedisDestination $destination
     * @param  int              $redeliveryDelay in seconds
     * @return Message|null
     */
    protected function receiveMessageNoWait(RedisDestination $destination, $redeliveryDelay)
    {
        $queueName = $destination->getName();
        $this->migrateExpiredMessages([$queueName]);

        $redeliveryAt = time() + $redeliveryDelay;
        $raw = $this->getContext()->getRedis()->evalString(
            self::atomicPopAndReserve(),
            [$queueName, $queueName . ':reserved'],
            [$redeliveryAt]
        );

        if ($raw !== null && $raw !== false) {
            return $this->processResult(new RedisResult($queueName, $raw));
        }

        return null;
    }

    /**
     * @param  RedisResult $result
     * @return Message|null
     */
    protected function processResult(RedisResult $result)
    {
        $message = $this->getContext()->getSerializer()->toMessage($result->getMessage());

        $now = time();

        if (0 === $message->getAttempts() && $expiresAt = $message->getHeader('expires_at')) {
            if ($now > $expiresAt) {
                // Remove the reservation that was created atomically so the
                // message does not get migrated back and re-processed forever.
                $this->getContext()->getRedis()->zrem(
                    $result->getKey() . ':reserved',
                    $result->getMessage()
                );
                return null;
            }
        }

        $message->setHeader('attempts', $message->getAttempts() + 1);
        $message->setRedelivered($message->getAttempts() > 1);
        $message->setKey($result->getKey());

        // The raw payload is the member stored in the :reserved sorted set by
        // atomicPopAndReserve(). acknowledge() must ZREM this exact value.
        $message->setReservedKey($result->getMessage());

        return $message;
    }

    /**
     * @param  string $queueName
     * @return void
     */
    protected function pushQueueNameBack($queueName)
    {
        if (count($this->queueNames) <= 1) {
            return;
        }

        if (false === $from = array_search($queueName, $this->queueNames, true)) {
            throw new \LogicException(sprintf('Queue name was not found: "%s"', $queueName));
        }

        $to = count($this->queueNames) - 1;

        $out = array_splice($this->queueNames, $from, 1);
        array_splice($this->queueNames, $to, 0, $out);
    }

    /**
     * @param  array $queueNames
     * @return void
     */
    protected function migrateExpiredMessages(array $queueNames)
    {
        $now = time();

        foreach ($queueNames as $queueName) {
            $this->getContext()->getRedis()
                ->evalString(self::migrateExpired(), [$queueName.':delayed', $queueName], [$now]);

            $this->getContext()->getRedis()
                ->evalString(self::migrateExpired(), [$queueName.':reserved', $queueName], [$now]);
        }
    }

    /**
     * Atomically pop one message from a queue list and add it to the reserved
     * sorted set in a single Lua call. This eliminates the window between RPOP
     * and ZADD where a process crash would lose the message.
     *
     * KEYS[1] - Source queue (list), e.g. my_queue
     * KEYS[2] - Reserved sorted set, e.g. my_queue:reserved
     * ARGV[1] - Redelivery-at UNIX timestamp (score)
     *
     * @return string
     */
    public static function atomicPopAndReserve()
    {
        return <<<'LUA'
local msg = redis.call('rpop', KEYS[1])
if msg then
    redis.call('zadd', KEYS[2], ARGV[1], msg)
    return msg
end
return false
LUA;
    }

     /**
      * Get the Lua script to migrate expired messages back onto the queue.
      *
      * KEYS[1] - The queue we are removing messages from, for example: queues:foo:reserved
      * KEYS[2] - The queue we are moving messages to, for example: queues:foo
      * ARGV[1] - The current UNIX timestamp
      *
      * @return string
      */
    public static function migrateExpired()
    {
        return <<<'LUA'
-- Get all of the messages with an expired "score"...
local val = redis.call('zrangebyscore', KEYS[1], '-inf', ARGV[1])

-- If we have values in the array, we will remove them from the first queue
-- and add them onto the destination queue in chunks of 100, which moves
-- all of the appropriate messages onto the destination queue very safely.
if(next(val) ~= nil) then
    redis.call('zremrangebyrank', KEYS[1], 0, #val - 1)

    for i = 1, #val, 100 do
        redis.call('lpush', KEYS[2], unpack(val, i, math.min(i+99, #val)))
    end
end

return val
LUA;
    }
}
