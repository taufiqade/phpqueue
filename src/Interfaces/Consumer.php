<?php

namespace Adsry\Interfaces;

use Adsry\Interfaces\Message;

interface Consumer
{
    public function getQueue();
    public function receive($timeout = 0);
    public function receiveNoWait();
    public function acknowledge(Message $message);
    public function reject(Message $message, $requeue = false);
}