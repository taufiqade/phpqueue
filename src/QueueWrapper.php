<?php
namespace Adsry;

use Adsry\Adapters\Redis\RedisTransporter;
use Adsry\Interfaces\Transporter;
use Adsry\Interfaces\Context;
use Exception;

class QueueWrapper
{
    CONST AVAILABLE_TRANSPORTER = [
        'redis' => RedisTransporter::class,
    ];

    private $transporter;
    private $config;
    
    /**
     * @param  string $transporter
     * @param  array  $config
     * @return void
     */
    public function __construct($transporter, array $config)
    {
        $this->transporter = $transporter;
        $this->config = $config;

        $this->getTransporter()->connect();
    }
    
    /**
     * @return Transporter|null
     */
    private function getTransporter()
    {
        if (array_key_exists($this->transporter, self::AVAILABLE_TRANSPORTER)) {
            $transporterClass = self::AVAILABLE_TRANSPORTER[$this->transporter];
            return new $transporterClass($this->config);
        }
        throw new Exception("No transporter available");
    }
    
    /**
     * @return Context
     */
    public function createContext()
    {
        return $this->getTransporter()->createContext();
    }
}