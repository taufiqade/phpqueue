<?php

namespace Adsry\Adapters\Redis;
use Adsry\Interfaces\Queue;
use Adsry\Interfaces\Topic;

class RedisDestination implements Queue, Topic
{
    /**
     * @var string
     */
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getQueueName()
    {
        return $this->getName();
    }
    
    /**
     * @return string
     */
    public function getTopicName()
    {
        return $this->getName();
    }
}
