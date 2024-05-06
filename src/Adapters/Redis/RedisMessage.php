<?php

namespace Adsry\Adapters\Redis;

use Adsry\Interfaces\Message;

class RedisMessage implements Message
{
    /**
     * @var string
     */
    private $body;

    /**
     * @var array
     */
    private $properties;

    /**
     * @var array
     */
    private $headers;

    /**
     * @var bool
     */
    private $redelivered;

    /**
     * @var string
     */
    private $reservedKey;

    /**
     * @var string
     */
    private $key;

    public function __construct($body = '', array $properties = [], array $headers = [])
    {
        $this->body = $body;
        $this->properties = $properties;
        $this->headers = $headers;

        $this->redelivered = false;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function setProperties(array $properties)
    {
        $this->properties = $properties;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function setProperty($name, $value)
    {
        $this->properties[$name] = $value;
    }

    public function getProperty($name, $default = null)
    {
        return array_key_exists($name, $this->properties) ? $this->properties[$name] : $default;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    public function getHeader($name, $default = null)
    {
        return array_key_exists($name, $this->headers) ? $this->headers[$name] : $default;
    }

    public function setRedelivered($redelivered)
    {
        $this->redelivered = $redelivered;
    }

    public function isRedelivered()
    {
        return $this->redelivered;
    }

    public function setCorrelationId($correlationId = null)
    {
        $this->setHeader('correlation_id', $correlationId);
    }

    public function getCorrelationId()
    {
        return $this->getHeader('correlation_id');
    }

    public function setMessageId($messageId = null)
    {
        $this->setHeader('message_id', $messageId);
    }

    public function getMessageId()
    {
        return $this->getHeader('message_id');
    }

    public function getTimestamp()
    {
        $value = $this->getHeader('timestamp');

        return null === $value ? null : (int) $value;
    }

    public function setTimestamp($timestamp = null)
    {
        $this->setHeader('timestamp', $timestamp);
    }

    public function setReplyTo($replyTo = null)
    {
        $this->setHeader('reply_to', $replyTo);
    }

    public function getReplyTo()
    {
        return $this->getHeader('reply_to');
    }

    /**
     * @return int
     */
    public function getAttempts()
    {
        return (int) $this->getHeader('attempts', 0);
    }

    /**
     * @return int
     */
    public function getTimeToLive()
    {
        return $this->getHeader('time_to_live');
    }

    /**
     * Set time to live in milliseconds.
     */
    public function setTimeToLive($timeToLive = null)
    {
        $this->setHeader('time_to_live', $timeToLive);
    }

    public function getDeliveryDelay()
    {
        return $this->getHeader('delivery_delay');
    }

    /**
     * Set delay in milliseconds.
     */
    public function setDeliveryDelay($deliveryDelay = null)
    {
        $this->setHeader('delivery_delay', $deliveryDelay);
    }

    /**
     * @return string
     */
    public function getReservedKey()
    {
        return $this->reservedKey;
    }

    /**
     * @param $reservedKey
     */
    public function setReservedKey($reservedKey)
    {
        $this->reservedKey = $reservedKey;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }
}
