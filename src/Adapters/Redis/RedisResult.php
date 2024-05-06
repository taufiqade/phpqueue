<?php

namespace Adsry\Adapters\Redis;

class RedisResult
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $message;

    public function __construct($key, $message)
    {
        $this->key = $key;
        $this->message = $message;
    }
    
    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }
    
    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
}
