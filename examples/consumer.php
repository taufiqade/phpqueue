<?php

require 'vendor/autoload.php';

use Adsry\QueueWrapper;

$queue = new QueueWrapper('redis', [
    'host' => '192.168.18.238',
    'port' => 6379,
]);

$context = $queue->createContext();

$queue = $context->createQueue('taufiq');
$consumer = $context->createConsumer($queue);

while (true) {
    sleep(5);
    try {
        $message = $consumer->receive();
        // ...
        // ...
        $consumer->acknowledge($message);
    } catch(Exception $e) {
        $consumer->reject($message, true);
    }
}
