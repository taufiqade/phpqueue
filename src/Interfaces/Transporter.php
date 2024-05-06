<?php

namespace Adsry\Interfaces;

interface Transporter
{
    public function connect();

    public function createContext();
}