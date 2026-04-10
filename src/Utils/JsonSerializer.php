<?php

namespace Adsry\Utils;

use Adsry\Adapters\Redis\RedisMessage;
use Adsry\Interfaces\Message;
use Adsry\Interfaces\Serializer;

class JsonSerializer implements Serializer
{
    public function toString(Message $message)
    {
        $json = json_encode(
            [
            'body' => $message->getBody(),
            'properties' => $message->getProperties(),
            'headers' => $message->getHeaders(),
            ]
        );

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The malformed json given. Error %s and message %s',
                    json_last_error(),
                    json_last_error_msg()
                )
            );
        }

        return $json;
    }

    public function toMessage($string)
    {
        $data = json_decode($string, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The malformed json given. Error %s and message %s',
                    json_last_error(),
                    json_last_error_msg()
                )
            );
        }

        $body       = isset($data['body'])       ? $data['body']       : '';
        $properties = isset($data['properties']) ? $data['properties'] : [];
        $headers    = isset($data['headers'])    ? $data['headers']    : [];

        return new RedisMessage($body, $properties, $headers);
    }
}
