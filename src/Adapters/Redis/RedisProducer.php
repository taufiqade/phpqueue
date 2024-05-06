<?php

namespace Adsry\Adapters\Redis;

use Adsry\Exceptions\AssertException;
use Adsry\Interfaces\Destination;
use Adsry\Interfaces\Message;
use Adsry\Interfaces\Producer;
use Ramsey\Uuid\Uuid;

class RedisProducer implements Producer
{
    /**
     * @var RedisContext
     */
    private $context;

    /**
     * @var int|null
     */
    private $timeToLive;

    /**
     * @var int
     */
    private $deliveryDelay;

    /**
     * @param RedisContext $context
     */
    public function __construct(RedisContext $context)
    {
        $this->context = $context;
    }
    
    /**
     * @param  RedisDestination $destination
     * @param  RedisMessage     $message
     * @return void
     */
    public function send(Destination $destination, Message $message)
    {
        AssertException::assertInstanceOf($destination, RedisDestination::class);
        AssertException::assertInstanceOf($message, RedisMessage::class);
        
        $message->setMessageId(Uuid::uuid4()->toString());
        $message->setHeader('attempts', 0);

        if (null !== $this->timeToLive && null === $message->getTimeToLive()) {
            $message->setTimeToLive($this->timeToLive);
        }

        if (null !== $this->deliveryDelay && null === $message->getDeliveryDelay()) {
            $message->setDeliveryDelay($this->deliveryDelay);
        }

        if ($message->getTimeToLive()) {
            $message->setHeader('expires_at', time() + $message->getTimeToLive());
        }

        $payload = $this->context->getSerializer()->toString($message);

        if ($message->getDeliveryDelay()) {
            $deliveryAt = time() + $message->getDeliveryDelay() / 1000;
            $this->context->getRedis()->zadd($destination->getName().':delayed', $payload, $deliveryAt);
        } else {
            $this->context->getRedis()->lpush($destination->getName(), $payload);
        }
    }

    /**
     * @return self
     */
    public function setDeliveryDelay($deliveryDelay = null)
    {
        $this->deliveryDelay = $deliveryDelay;

        return $this;
    }    
    /**
     * @return int
     */
    public function getDeliveryDelay()
    {
        return $this->deliveryDelay;
    }
    
    /**
     * @param  mixed $timeToLive
     * @return self
     */
    public function setTimeToLive($timeToLive = null)
    {
        $this->timeToLive = $timeToLive;

        return $this;
    }    
    /**
     * @return int|null
     */
    public function getTimeToLive()
    {
        return $this->timeToLive;
    }
}