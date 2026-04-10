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

    private $transporterName;
    private $config;
    private $transporterInstance;

    /**
     * @param  string $transporter
     * @param  array  $config
     * @return void
     */
    public function __construct($transporter, array $config)
    {
        $this->transporterName = $transporter;
        $this->config = $config;

        $this->getTransporter()->connect();
    }

    /**
     * @return Transporter
     */
    private function getTransporter()
    {
        if ($this->transporterInstance === null) {
            if (!array_key_exists($this->transporterName, self::AVAILABLE_TRANSPORTER)) {
                throw new Exception("No transporter available: {$this->transporterName}");
            }
            $transporterClass = self::AVAILABLE_TRANSPORTER[$this->transporterName];
            $this->transporterInstance = new $transporterClass($this->config);
        }

        return $this->transporterInstance;
    }
    
    /**
     * @return Context
     */
    public function createContext()
    {
        return $this->getTransporter()->createContext();
    }
}