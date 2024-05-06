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
     * @param RedisDestination[] $queues
     * @param int                $timeout
     * @param int                $redeliveryDelay
     *
     * @return RedisMessage|null
     */
    protected function receiveMessage(array $queues, $timeout, $redeliveryDelay)
    {
        $startAt = time();
        $thisTimeout = $timeout;

        if (null === $this->queueNames) {
            $this->queueNames = [];
            foreach ($queues as $queue) {
                $this->queueNames[] = $queue->getName();
            }
        }

        while ($thisTimeout > 0) {
            $this->migrateExpiredMessages($this->queueNames);

            if (false == $result = $this->getContext()->getRedis()->brpop($this->queueNames, $thisTimeout)) {
                return null;
            }

            $this->pushQueueNameBack($result->getKey());

            if ($message = $this->processResult($result, $redeliveryDelay)) {
                return $message;
            }

            $thisTimeout -= time() - $startAt;
        }

        return null;
    }
    
    /**
     * @param  mixed $destination
     * @param  mixed $redeliveryDelay
     * @return Message|null
     */
    protected function receiveMessageNoWait(RedisDestination $destination, $redeliveryDelay)
    {
        $this->migrateExpiredMessages([$destination->getName()]);

        if ($result = $this->getContext()->getRedis()->rpop($destination->getName())) {
            return $this->processResult($result, $redeliveryDelay);
        }

        return null;
    }
    
    /**
     * @param  mixed $result
     * @param  mixed $redeliveryDelay
     * @return Message|null
     */
    protected function processResult(RedisResult $result, $redeliveryDelay)
    {
        $message = $this->getContext()->getSerializer()->toMessage($result->getMessage());
        // $message = unserialize($result->getMessage());

        $now = time();

        if (0 === $message->getAttempts() && $expiresAt = $message->getHeader('expires_at')) {
            if ($now > $expiresAt) {
                return null;
            }
        }

        $message->setHeader('attempts', $message->getAttempts() + 1);
        $message->setRedelivered($message->getAttempts() > 1);
        $message->setKey($result->getKey());
        $message->setReservedKey($this->getContext()->getSerializer()->toString($message));

        $reservedQueue = $result->getKey().':reserved';
        $redeliveryAt = $now + $redeliveryDelay;

        $this->getContext()->getRedis()->zadd($reservedQueue, $message->getReservedKey(), $redeliveryAt);

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
