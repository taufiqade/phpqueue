<?php

namespace Adsry\Adapters\Redis;

use Adsry\Exceptions\AssertException;
use Adsry\Interfaces\Consumer;
use Adsry\Interfaces\Context;
use Adsry\Interfaces\Message;
use Adsry\Interfaces\Redis;

class RedisConsumer implements Consumer
{
    use RedisConsumerHelperTrait;

    /**
     * @var RedisContext $context 
     */
    private $context;

    /**
     * @var RedisDestination $queue 
     */
    private $queue;
    
    /**
     * @var int 
     */
    private $redeliveryDelay = 300;
    
    public function __construct(RedisContext $context, RedisDestination $queue)
    {
        $this->context = $context;
        $this->queue = $queue;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @return int
     */
    public function getRedeliveryDelay()
    {
        return $this->redeliveryDelay;
    }
    
    /**
     * @param  mixed $delay
     * @return void
     */
    public function setRedeliveryDelay($delay)
    {
        $this->redeliveryDelay = $delay;
    }

    /**
     * @return RedisMessage
     */
    public function receive($timeout = 0)
    {
        $timeout = (int) ceil($timeout / 1000);

        echo "start waiting connection ... \n";
        if ($timeout <= 0) {
            while (true) {
                echo "waiting connection ... \n";
                if ($message = $this->receive(5000)) {
                    return $message;
                }
            }
        }

        return $this->receiveMessage([$this->queue], $timeout, $this->redeliveryDelay);
    }

    public function receiveNoWait()
    {

    }

    /**
     * @param RedisMessage $message
     */
    public function acknowledge(Message $message)
    {
        $this->getRedis()->zrem($this->queue->getName().':reserved', $message->getReservedKey());
    }

    /**
     * @param RedisMessage $message
     */
    public function reject(Message $message, $requeue = false)
    {
        AssertException::assertInstanceOf($message, RedisMessage::class);

        if (!$requeue) {
            $this->acknowledge($message);
            return;
        }

        // Build the requeue payload from the live message object (not from the
        // reserved-key snapshot) so any mutations made before reject() are kept.
        $message->setRedelivered(true);
        if ($message->getTimeToLive()) {
            $message->setHeader('expires_at', time() + $message->getTimeToLive());
        }
        $payload = $this->getContext()->getSerializer()->toString($message);

        // Atomically remove from :reserved and push back to the main queue so
        // a crash between the two operations cannot lose the message.
        $this->getContext()->getRedis()->evalString(
            self::atomicAcknowledgeAndRequeue(),
            [$this->queue->getName() . ':reserved', $this->queue->getName()],
            [$message->getReservedKey(), $payload]
        );
    }
    
    /**
     * @return RedisContext
     */
    protected function getContext()
    {
        return $this->context;
    }
    
    /**
     * @return Redis
     */
    private function getRedis()
    {
        return $this->context->getRedis();
    }
}
