<?php

require 'vendor/autoload.php';

use Adsry\QueueWrapper;

$queue = new QueueWrapper('redis', [
    'host' => '192.168.18.238',
    'port' => 6379,
]);

$context = $queue->createContext();

$queue = $context->createQueue('queue_taufiq');
$producer = $context->createProducer();

for ($i = 0; $i < 4; $i++) {
    $message = $context->createMessage("send message ". $i);
    $producer->send($queue, $message);
}
