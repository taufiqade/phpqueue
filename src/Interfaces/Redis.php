<?php
namespace Adsry\Interfaces;

use Adsry\Adapters\Redis\RedisResult;
use Adsry\Exceptions\RedisException;

interface Redis
{
    /**
     * @param $script
     * @param array $keys
     * @param array $args
     *
     * @throws RedisException
     *
     * @return mixed
     */
    public function evalString($script, array $keys = [], array $args = []);

    /**
     * @param $key
     * @param $value
     * @param float $score
     *
     * @throws RedisException
     *
     * @return int
     */
    public function zadd($key, $value, $score);

    /**
     * @param $key
     * @param $value
     *
     * @throws RedisException
     *
     * @return int
     */
    public function zrem($key, $value);

    /**
     * @param $key
     * @param $value
     *
     * @throws RedisException
     *
     * @return int length of the list
     */
    public function lpush($key, $value);

    /**
     * @throws RedisException
     */
    public function connect();

    public function disconnect();

    /**
     * @param $key
     *
     * @throws RedisException
     */
    public function del($key);
}
