<?php

namespace Adsry\Adapters\Redis;

use Adsry\Interfaces\Context;
use Adsry\Interfaces\Destination;
use Adsry\Interfaces\Queue;
use Adsry\Utils\JsonSerializer;
use Adsry\Utils\SerializerAwareTrait;

class RedisContext implements Context
{
    use SerializerAwareTrait;

    private $redis;
    public function __construct($redis)
    {
        $this->redis = $redis;

        $this->setSerializer(new JsonSerializer());
    }
    
    /**
     * @param  string $body
     * @param  array  $properties
     * @param  array  $headers
     * @return RedisMessage
     */
    public function createMessage($body = '', array $properties = [], array $headers = [])
    {
        return new RedisMessage($body, $properties, $headers);
    }

    /**
     * @param  mixed $topicName
     * @return RedisDestination
     */
    public function createTopic($topicName)
    {
        return new RedisDestination($topicName);
    }  
  
    /**
     * @param  mixed $queueName
     * @return RedisDestination
     */
    public function createQueue($queueName)
    {
        return new RedisDestination($queueName);
    }
    
    /**
     * @return RedisProducer
     */
    public function createProducer()
    {
        return new RedisProducer($this);
    }
        
    /**
     * @param  RedisDestination $destination
     * @return RedisConsumer
     */
    public function createConsumer(Destination $destination)
    {
        return new RedisConsumer($this, $destination);
    }

    public function createSubscriptionConsumer()
    {

    }
    
    /**
     * @param  Queue $queue
     * @return void
     */
    public function purgeQueue(Queue $queue)
    {
        $this->deleteDestination($queue);
    }

    /**
     * @param  RedisDestination $destination
     * @return void
     */
    private function deleteDestination(RedisDestination $destination)
    {
        $this->getRedis()->del($destination->getName());
        $this->getRedis()->del($destination->getName().':delayed');
        $this->getRedis()->del($destination->getName().':reserved');
    }

    public function getRedis()
    {
        return $this->redis;
    }

    public function close()
    {
        $this->getRedis()->disconnect();
    }
}