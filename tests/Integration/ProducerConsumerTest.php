<?php

namespace Adsry\Tests\Integration;

use Adsry\Adapters\Redis\RedisMessage;

/**
 * Full producer→consumer lifecycle tests against a real Redis instance.
 *
 * Set REDIS_HOST / REDIS_PORT env vars to point at your Redis.
 * Tests are skipped automatically when Redis is unreachable.
 */
class ProducerConsumerTest extends RedisIntegrationTestCase
{
    // -------------------------------------------------------------------------
    // Basic send / receive / acknowledge
    // -------------------------------------------------------------------------

    public function testProducedMessageIsReceivedAndAcknowledged()
    {
        $queue    = $this->context->createQueue($this->queueName);
        $producer = $this->context->createProducer();
        $consumer = $this->context->createConsumer($queue);

        $producer->send($queue, $this->context->createMessage('hello world'));

        $message = $consumer->receive(2000);

        $this->assertNotNull($message);
        $this->assertSame('hello world', $message->getBody());
        $this->assertNotNull($message->getMessageId(), 'Producer must assign a UUID');

        $consumer->acknowledge($message);

        // Queue and :reserved must both be empty after ack
        $this->assertNull($consumer->receive(500), 'Queue must be empty after acknowledge');
        $this->assertRedisKeyEmpty($this->queueName . ':reserved');
    }

    public function testMultipleMessagesReceivedInFifoOrder()
    {
        $queue    = $this->context->createQueue($this->queueName);
        $producer = $this->context->createProducer();
        $consumer = $this->context->createConsumer($queue);

        foreach (['first', 'second', 'third'] as $body) {
            $producer->send($queue, $this->context->createMessage($body));
        }

        $received = [];
        for ($i = 0; $i < 3; $i++) {
            $msg = $consumer->receive(2000);
            $this->assertNotNull($msg);
            $received[] = $msg->getBody();
            $consumer->acknowledge($msg);
        }

        $this->assertSame(['first', 'second', 'third'], $received);
    }

    // -------------------------------------------------------------------------
    // C3 / C4 — reject with requeue
    // -------------------------------------------------------------------------

    public function testRejectedMessageIsRequeuedWithLiveBody()
    {
        $queue    = $this->context->createQueue($this->queueName);
        $producer = $this->context->createProducer();
        $consumer = $this->context->createConsumer($queue);

        $producer->send($queue, $this->context->createMessage('original'));

        $first = $consumer->receive(2000);
        $this->assertNotNull($first);
        $this->assertSame('original', $first->getBody());

        // Mutate the live message before rejecting — C4 verifies this body is preserved
        $first->setBody('mutated before reject');
        $consumer->reject($first, true);

        $second = $consumer->receive(2000);
        $this->assertNotNull($second, 'Rejected message must reappear in the queue');
        $this->assertSame('mutated before reject', $second->getBody(),
            'C4: requeue must use the live message body, not the stale snapshot');
        $this->assertTrue($second->isRedelivered(), 'Requeued message must be flagged as redelivered');

        $consumer->acknowledge($second);
    }

    public function testRejectedWithoutRequeueDiscardsMessage()
    {
        $queue    = $this->context->createQueue($this->queueName);
        $producer = $this->context->createProducer();
        $consumer = $this->context->createConsumer($queue);

        $producer->send($queue, $this->context->createMessage('discard me'));

        $message = $consumer->receive(2000);
        $this->assertNotNull($message);
        $consumer->reject($message, false);

        $this->assertNull($consumer->receive(500), 'Discarded message must not reappear');
        $this->assertRedisKeyEmpty($this->queueName . ':reserved');
    }

    // -------------------------------------------------------------------------
    // Delayed delivery
    // -------------------------------------------------------------------------

    public function testDelayedMessageIsNotImmediatelyVisible()
    {
        $queue    = $this->context->createQueue($this->queueName);
        $producer = $this->context->createProducer();
        $consumer = $this->context->createConsumer($queue);

        $message = $this->context->createMessage('delayed');
        $message->setDeliveryDelay(5000); // 5 seconds

        $producer->send($queue, $message);

        // Must not be visible yet
        $this->assertNull($consumer->receive(500),
            'Delayed message must not be available before the delay expires');

        // The :delayed sorted set must contain the message
        $this->assertRedisKeyNotEmpty($this->queueName . ':delayed');
    }

    // -------------------------------------------------------------------------
    // Redelivery via :reserved migration
    // -------------------------------------------------------------------------

    public function testUnacknowledgedMessageIsRedeliveredAfterRedeliveryDelay()
    {
        $queue    = $this->context->createQueue($this->queueName);
        $producer = $this->context->createProducer();
        $consumer = $this->context->createConsumer($queue);
        $consumer->setRedeliveryDelay(1); // 1 second for test speed

        $producer->send($queue, $this->context->createMessage('redeliver me'));

        // Receive but do NOT acknowledge — simulates a stalled consumer
        $first = $consumer->receive(2000);
        $this->assertNotNull($first);

        // Wait for redelivery delay to expire, then a new consumer picks it up
        sleep(2);

        $consumer2 = $this->context->createConsumer($queue);
        $consumer2->setRedeliveryDelay(1);

        $second = $consumer2->receive(3000);
        $this->assertNotNull($second, 'Message must be redelivered after redelivery delay');
        $this->assertSame('redeliver me', $second->getBody());
        $this->assertTrue($second->isRedelivered(), 'Must be flagged as redelivered');

        $consumer2->acknowledge($second);
    }

    // -------------------------------------------------------------------------
    // C1 — context reuse: same connection, no duplicate reconnect
    // -------------------------------------------------------------------------

    public function testCreateContextReturnsFunctionalContextEachTime()
    {
        // Both contexts must be independently functional (share the same underlying connection)
        $ctx1 = $this->wrapper->createContext();
        $ctx2 = $this->wrapper->createContext();

        $q1 = $ctx1->createQueue($this->queueName);
        $ctx1->createProducer()->send($q1, $ctx1->createMessage('via ctx1'));

        $q2 = $ctx2->createQueue($this->queueName);
        $msg = $ctx2->createConsumer($q2)->receive(2000);

        $this->assertNotNull($msg);
        $this->assertSame('via ctx1', $msg->getBody());

        $ctx2->createConsumer($q2)->acknowledge($msg);
        $ctx1->close();
        $ctx2->close();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertRedisKeyEmpty($key)
    {
        $exists = $this->context->getRedis()->evalString(
            "return redis.call('exists', KEYS[1])",
            [$key],
            []
        );
        $this->assertSame(0, (int) $exists, "Expected Redis key '{$key}' to be empty/absent");
    }

    private function assertRedisKeyNotEmpty($key)
    {
        $exists = $this->context->getRedis()->evalString(
            "return redis.call('exists', KEYS[1])",
            [$key],
            []
        );
        $this->assertSame(1, (int) $exists, "Expected Redis key '{$key}' to exist");
    }
}
