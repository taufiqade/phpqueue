<?php

namespace Adsry\Adapters\Redis;

use Adsry\Interfaces\Redis;
use Exception;
use Predis\Client;
use Predis\Response\ServerException;

class Predis implements Redis
{
    private $parameters;

    private $options;

    private $redis;

    public function __construct(array $config)
    {
        if (false == class_exists(Client::class)) {
            throw new Exception('The package "predis/predis" must be installed. Please run "composer req predis/predis:^1.1" to install it');
        }

        $this->options = $config['predis_options'];

        $this->parameters = [
            'scheme' => $config['scheme'],
            'host' => $config['host'],
            'port' => $config['port'],
            'password' => $config['password'],
            'database' => $config['database'],
            'path' => $config['path'],
            'async' => $config['async'],
            'persistent' => $config['persistent'],
            'timeout' => $config['timeout'],
            'read_write_timeout' => $config['read_write_timeout'],
        ];

        if ($config['ssl']) {
            $this->parameters['ssl'] = $config['ssl'];
        }
    }

    public function evalString($script, array $keys = [], array $args = [])
    {
        try {
            return call_user_func_array([$this->redis, 'eval'], array_merge([$script, count($keys)], $keys, $args));
        } catch (ServerException $e) {
            throw new ServerException('eval command has failed', 0, $e);
        }
    }

    public function zadd($key, $value, $score)
    {
        try {
            return $this->redis->zadd($key, [$value => $score]);
        } catch (ServerException $e) {
            throw new ServerException('zadd command has failed', 0, $e);
        }
    }

    public function zrem($key, $value)
    {
        try {
            return $this->redis->zrem($key, [$value]);
        } catch (ServerException $e) {
            throw new ServerException('zrem command has failed', 0, $e);
        }
    }

    public function lpush($key, $value)
    {
        try {
            return $this->redis->lpush($key, [$value]);
        } catch (ServerException $e) {
            throw new ServerException('lpush command has failed', 0, $e);
        }
    }

    public function brpop(array $keys, $timeout)
    {
        try {
            if ($result = $this->redis->brpop($keys, $timeout)) {
                return new RedisResult($result[0], $result[1]);
            }

            return null;
        } catch (ServerException $e) {
            throw new ServerException('brpop command has failed', 0, $e);
        }
    }

    public function rpop($key)
    {
        try {
            if ($message = $this->redis->rpop($key)) {
                return new RedisResult($key, $message);
            }

            return null;
        } catch (ServerException $e) {
            throw new ServerException('rpop command has failed', 0, $e);
        }
    }

    public function connect()
    {
        if ($this->redis) {
            return;
        }

        $this->redis = new Client($this->parameters, $this->options);

        $this->redis->connect();
    }

    public function disconnect()
    {
        $this->redis->disconnect();
    }

    public function del($key)
    {
        $this->redis->del([$key]);
    }
}