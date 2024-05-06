<?php

namespace Adsry\Interfaces;

interface Context
{
    public function createMessage($body = '', array $properties = [], array $headers = []);

    public function createTopic($topicName);

    public function createQueue($queueName);

    public function createProducer();

    public function createConsumer(Destination $destination);

    public function createSubscriptionConsumer();

    public function purgeQueue(Queue $queue);

    public function close();
}