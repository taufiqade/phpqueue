<?php

namespace Adsry\Interfaces;

interface Producer
{
    public function send(Destination $destination, Message $message);
    public function setDeliveryDelay($deliveryDelay = null);
    public function getDeliveryDelay();
    public function setTimeToLive($timeToLive = null);
    public function getTimeToLive();
}