<?php

require __DIR__ . '/vendor/autoload.php';

use Adsry\QueueWrapper;

// ─── Config ──────────────────────────────────────────────────────────────────
$REDIS_HOST = getenv('REDIS_HOST') ?: 'redis';
$REDIS_PORT = getenv('REDIS_PORT') ?: 6379;
$QUEUE_NAME = 'test_queue';

echo "=== phpqueue test script ===" . PHP_EOL;
echo "Redis: {$REDIS_HOST}:{$REDIS_PORT}" . PHP_EOL . PHP_EOL;

// ─── Helper ──────────────────────────────────────────────────────────────────
function pass($label) {
    echo "[PASS] {$label}" . PHP_EOL;
}

function fail($label, $reason = '') {
    echo "[FAIL] {$label}" . ($reason ? " — {$reason}" : '') . PHP_EOL;
}

// ─── Connect ─────────────────────────────────────────────────────────────────
try {
    $wrapper = new QueueWrapper('redis', [
        'host' => $REDIS_HOST,
        'port' => (int) $REDIS_PORT,
    ]);
    $context = $wrapper->createContext();
    pass('Connected to Redis');
} catch (Exception $e) {
    fail('Connect', $e->getMessage());
    exit(1);
}

// ─── Setup ───────────────────────────────────────────────────────────────────
$queue    = $context->createQueue($QUEUE_NAME);
$producer = $context->createProducer();
$consumer = $context->createConsumer($queue);

// Clear any leftover state from previous runs
$context->purgeQueue($queue);
pass('Queue purged');

// ─── Test 1: Send & receive a simple message ─────────────────────────────────
echo PHP_EOL . "--- Test 1: Send & receive a plain message ---" . PHP_EOL;

$producer->send($queue, $context->createMessage('hello world'));

$msg = $consumer->receive(3000);
if ($msg && $msg->getBody() === 'hello world') {
    pass('Received correct body: ' . $msg->getBody());
    $consumer->acknowledge($msg);
    pass('Acknowledged message');
} else {
    fail('Receive plain message', $msg ? 'wrong body: ' . $msg->getBody() : 'no message');
}

// ─── Test 2: Message with properties & headers ───────────────────────────────
echo PHP_EOL . "--- Test 2: Properties & headers ---" . PHP_EOL;

$msg = $context->createMessage(
    'structured payload',
    ['type' => 'order', 'priority' => 1],
    ['source' => 'test-script']
);
$producer->send($queue, $msg);

$received = $consumer->receive(3000);
if ($received) {
    $ok = $received->getProperty('type') === 'order'
       && $received->getProperty('priority') === 1
       && $received->getHeader('source') === 'test-script';
    $ok ? pass('Properties & headers preserved') : fail('Properties or headers mismatch');
    $consumer->acknowledge($received);
} else {
    fail('Receive message with properties', 'no message returned');
}

// ─── Test 3: Multiple messages & ordering (FIFO) ─────────────────────────────
echo PHP_EOL . "--- Test 3: Multiple messages (FIFO order) ---" . PHP_EOL;

$count = 5;
for ($i = 1; $i <= $count; $i++) {
    $producer->send($queue, $context->createMessage("msg-{$i}"));
}

$order = [];
for ($i = 0; $i < $count; $i++) {
    $m = $consumer->receive(3000);
    if ($m) {
        $order[] = $m->getBody();
        $consumer->acknowledge($m);
    }
}

$expected = array_map(function ($i) { return "msg-{$i}"; }, range(1, $count));
if ($order === $expected) {
    pass('FIFO order correct: ' . implode(', ', $order));
} else {
    fail('FIFO order', 'got: ' . implode(', ', $order));
}

// ─── Test 4: Reject & requeue ────────────────────────────────────────────────
echo PHP_EOL . "--- Test 4: Reject with requeue ---" . PHP_EOL;

$producer->send($queue, $context->createMessage('requeue-me'));

$first = $consumer->receive(3000);
if ($first && $first->getBody() === 'requeue-me') {
    $consumer->reject($first, true);   // requeue = true
    pass('Message rejected with requeue');

    $second = $consumer->receive(3000);
    if ($second && $second->getBody() === 'requeue-me' && $second->isRedelivered()) {
        pass('Redelivered message received, isRedelivered=true');
        $consumer->acknowledge($second);
    } else {
        fail('Redelivered message', $second ? 'isRedelivered=' . var_export($second->isRedelivered(), true) : 'no message');
    }
} else {
    fail('Receive message for reject test', 'no message');
}

// ─── Test 5: Reject without requeue (discard) ────────────────────────────────
echo PHP_EOL . "--- Test 5: Reject without requeue (discard) ---" . PHP_EOL;

$producer->send($queue, $context->createMessage('discard-me'));

$m = $consumer->receive(3000);
if ($m && $m->getBody() === 'discard-me') {
    $consumer->reject($m, false);   // requeue = false → discard
    pass('Message rejected and discarded');
} else {
    fail('Receive message for discard test');
}

// ─── Test 6: Delivery delay ──────────────────────────────────────────────────
echo PHP_EOL . "--- Test 6: Delivery delay (2 000 ms) ---" . PHP_EOL;

$producer->setDeliveryDelay(2000);   // 2 seconds
$producer->send($queue, $context->createMessage('delayed-msg'));
$producer->setDeliveryDelay(null);   // reset

// Should NOT be available immediately
$tooEarly = $consumer->receive(500);
if (!$tooEarly) {
    pass('Message not yet available before delay');
} else {
    fail('Message available too early (delay not applied)');
    $consumer->acknowledge($tooEarly);
}

// Wait and then it should arrive
sleep(3);
$delayed = $consumer->receive(3000);
if ($delayed && $delayed->getBody() === 'delayed-msg') {
    pass('Delayed message received after waiting: ' . $delayed->getBody());
    $consumer->acknowledge($delayed);
} else {
    fail('Delayed message not received after wait', $delayed ? $delayed->getBody() : 'null');
}

// ─── Test 7: Time-to-live (TTL) ──────────────────────────────────────────────
echo PHP_EOL . "--- Test 7: Time-to-live (TTL) ---" . PHP_EOL;

$msgTtl = $context->createMessage('ttl-msg');
$msgTtl->setTimeToLive(2);   // 2 seconds TTL
$producer->send($queue, $msgTtl);
pass('TTL message sent (expires in 2 s)');

// The message should still carry the expires_at header
$ttlReceived = $consumer->receive(3000);
if ($ttlReceived && $ttlReceived->getHeader('expires_at')) {
    pass('expires_at header set: ' . $ttlReceived->getHeader('expires_at'));
    $consumer->acknowledge($ttlReceived);
} else {
    fail('TTL / expires_at header', $ttlReceived ? 'header missing' : 'no message received');
}

// ─── Done ─────────────────────────────────────────────────────────────────────
echo PHP_EOL . "=== All tests finished ===" . PHP_EOL;
$context->close();
