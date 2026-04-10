<?php

require 'vendor/autoload.php';

use Adsry\QueueWrapper;

$queue = new QueueWrapper('redis', [
    'host' => 'redis',
    'port' => 6379,
]);

$context = $queue->createContext();

$queue = $context->createQueue('queue_test');
$consumer = $context->createConsumer($queue);

while (true) {
    $message = null;
    try {
        $message = $consumer->receive();
        echo "Received: " . $message->getBody() . PHP_EOL;
        $consumer->acknowledge($message);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . PHP_EOL;
        if ($message !== null) {
            $consumer->reject($message, true);
        }
    }
}
