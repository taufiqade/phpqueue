<?php

namespace Adsry\Interfaces;

interface Queue extends Destination
{
    public function getQueueName();
}