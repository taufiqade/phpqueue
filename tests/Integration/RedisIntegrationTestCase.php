<?php

namespace Adsry\Tests\Integration;

use Adsry\Adapters\Redis\RedisContext;
use Adsry\QueueWrapper;
use PHPUnit\Framework\TestCase;

abstract class RedisIntegrationTestCase extends TestCase
{
    /** @var QueueWrapper */
    protected $wrapper;

    /** @var RedisContext */
    protected $context;

    /** @var string */
    protected $queueName;

    protected function setUp(): void
    {
        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);

        try {
            $this->wrapper = new QueueWrapper('redis', ['host' => $host, 'port' => $port]);
            $this->context = $this->wrapper->createContext();
        } catch (\Exception $e) {
            $this->markTestSkipped("Redis not available at {$host}:{$port} — {$e->getMessage()}");
        }

        // Unique queue per test to avoid cross-test contamination
        $this->queueName = 'phpqueue_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if ($this->context) {
            $this->context->purgeQueue($this->context->createQueue($this->queueName));
            $this->context->close();
        }
    }
}
