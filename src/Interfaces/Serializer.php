<?php

namespace Adsry\Interfaces;

interface Serializer
{
    public function toString(Message $message);

    public function toMessage($string);
}