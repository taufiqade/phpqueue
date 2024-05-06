<?php

namespace Adsry\Interfaces;

interface Message
{
    public function getBody();
    public function setBody($body);
    public function setProperties(array $properties);
    public function getProperties();
    public function setProperty($name, $value);
    public function getProperty($name, $default = null);
    public function setHeaders(array $headers);
    public function getHeaders();
    public function setHeader($name, $value);
    public function getHeader($name, $default = null);
    public function setRedelivered($redelivered);
    public function isRedelivered();
    public function setCorrelationId($correlationId = null);
    public function getCorrelationId();
    public function setMessageId($messageId = null);
    public function getMessageId();
    public function getTimestamp();
    public function setTimestamp($timestamp = null);
    public function setReplyTo($replyTo = null);
    public function getReplyTo();
}