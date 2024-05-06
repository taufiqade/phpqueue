<?php

namespace Adsry\Adapters\Redis;

use Adsry\Interfaces\Transporter;

class RedisTransporter implements Transporter
{
    /**
     * @var array REDIS_CONFIG
     */
    const REDIS_CONFIG = [
        'scheme' => 'redis',
        'scheme_extensions' => [],
        'host' => '127.0.0.1',
        'port' => 6379,
        'path' => null,
        'database' => null,
        'password' => null,
        'async' => false,
        'persistent' => false,
        'lazy' => true,
        'timeout' => 5.0,
        'read_write_timeout' => null,
        'predis_options' => null,
        'ssl' => null,
        'redelivery_delay' => 300,
    ];

    /**
     * @var Predis
     */
    private $redis;

    /**
     * @var array
     */
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    /**
     * @param  array $config
     * @return void
     */
    public function connect()
    {
        $this->createRedis();
    }
    
    /**
     * @return Predis
     */
    private function createRedis()
    {
        if (is_null($this->redis)) {
            $config = array_merge(self::REDIS_CONFIG, $this->config);

            $this->redis = new Predis($config);
    
            $this->redis->connect();
        }

        return $this->redis;
    }
    
    /**
     * @return RedisContext
     */
    public function createContext()
    {
        return new RedisContext($this->createRedis());
    }
}