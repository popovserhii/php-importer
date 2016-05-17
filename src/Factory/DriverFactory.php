<?php
/**
 * Driver Factory
 *
 * @category Agere
 * @package Agere_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 19.12.15 17:44
 */
namespace Agere\Importer\Factory;

use Zend\Stdlib\Exception;
use Agere\Importer\Driver;

class DriverFactory
{
    /** @var array */
    protected $config = [];

    /** @var array */
    protected $drivers = [
        'libxl' => Driver\LibXl::class,
        'csv' => Driver\Csv::class,
    ];

    public function __construct(array $config)
    {
        // standardizes config key
        foreach ($config['tasks'] as $key => $value) {
            unset($config['tasks'][$key]);
            $config['tasks'][$this->getConfigKey($key)] = $value;
        }

        $this->config = $config;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function create($configType)
    {
        $taskKey = $this->getConfigKey($configType);
        if (!isset($this->config['tasks'][$taskKey])) {
            throw new Exception\RuntimeException(
                sprintf('Import task "%s" (alias:%s] not registered', $taskKey, $configType)
            );
        }

        $config = $this->config['tasks'][$taskKey];


        if (!isset($config['driver'])) {
            throw new Exception\RuntimeException('Driver key must be set in the configuration array');
        }

        //if (is_array($config['driver'])) {
            //$driverKey = $config['driver']['name'];
        //} else {
            //$driverKey = $config['driver'];
        //}

        $driverKey = strtolower($config['driver']);
        if (isset($this->drivers[$driverKey])) {
            $driverClass = $this->drivers[$driverKey];
        } elseif (isset($this->config['class'])) {
            $driverClass = $this->config['class'];
        } else {
            throw new Exception\RuntimeException('Any driver not registered for ' . $driverKey);
        }

        $config += isset($this->config['driver_options'][$driverKey])
            ? $this->config['driver_options'][$driverKey]
            : [];

        $driver = new $driverClass($config);

        return $driver;
    }

    /**
     * Get standardizes config key
     *
     * @param $key
     * @return string
     */
    protected function getConfigKey($key)
    {
        return strtolower(preg_replace("/[^A-Za-z0-9]/", '', $key));
    }
}
