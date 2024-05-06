## Introduction
This package is used for managing queues in old PHP Version 5.6 Morbis, currently supporting Redis and soon expanding to include RabbitMQ, Kafka, and other message brokers.

## Prerequisites
- PHP 5.6 or later
- Redis

## Setup

in your composer.json file, add the following snippet.

```
"repositories": [
    {
        "type": "vcs",
        "url": "git@gitlab.com:devs/phpqueue-sdk.git"
    }
]
```

run command to install the dependency
```
composer require adsry/phpqueue
```

### Create Context
```
use Adsry\QueueWrapper;

$queue = new QueueWrapper('redis', [
    'host' => 'localhost',
    'port' => 6379,
]);

$context = $queue->createContext();
```

### Create queue and publish message
```
$queue = $context->createQueue('foo');
$producer = $context->createProducer();

for ($i = 0; $i < 4; $i++) {
    $message = $context->createMessage("send message ". $i);
    $producer->send($queue, $message);
}
```
### Delayed message
```
$message = $context->createMessage('Hello world!');
$context->createProducer()
    ->setDeliveryDelay(60000) // 60 sec
    ->send($fooQueue, $message)
;
```
### Create consumer
```
$fooQueue = $context->createQueue('aQueue');
$consumer = $context->createConsumer($fooQueue);

$message = $consumer->receive();

// process a message

$consumer->acknowledge($message);
// or can reject when throw an error
//$consumer->reject($message);
```
