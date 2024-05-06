<?php
namespace Adsry\Interfaces;

use Adsry\Adapters\Redis\RedisResult;
use Predis\Response\ServerException;

interface Redis
{
    /**
     * @param $script
     * @param array $keys
     * @param array $args
     *
     * @throws ServerException
     *
     * @return mixed
     */
    public function evalString($script, array $keys = [], array $args = []);

    /**
     * @param $key
     * @param $value
     * @param float $score
     *
     * @throws ServerException
     *
     * @return int
     */
    public function zadd($key, $value, $score);

    /**
     * @param $key
     * @param $value
     *
     * @throws ServerException
     *
     * @return int
     */
    public function zrem($key, $value);

    /**
     * @param $key
     * @param $value
     *
     * @throws ServerException
     *
     * @return int length of the list
     */
    public function lpush($key, $value);

    /**
     * @param string[] $keys
     * @param int      $timeout in seconds
     *
     * @throws ServerException
     *
     * @return RedisResult|null
     */
    public function brpop(array $keys, $timeout);

    /**
     * @param $key
     *
     * @throws ServerException
     *
     * @return RedisResult|null
     */
    public function rpop($key);

    /**
     * @throws ServerException
     */
    public function connect();

    public function disconnect();

    /**
     * @param $key
     *
     * @throws ServerException
     */
    public function del($key);
}
