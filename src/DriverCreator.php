<?php
/**
 * Driver Factory
 *
 * @category Popov
 * @package Popov_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 19.12.15 17:44
 */
namespace Popov\Importer;

use Psr\Container\ContainerInterface;
use Zend\Stdlib\Exception;
use Popov\Importer\Driver;

class DriverCreator
{
    /** @var ContainerInterface */
    protected $container;

    /** @var array */
    protected $config = [];

    /** @var array */
    protected $drivers = [
        'libxl' => Driver\LibXl::class,
        'csv' => Driver\Csv::class,
        'soap' => Driver\Soap::class,
    ];

    public function __construct(array $config, ContainerInterface $container = null)
    {
        $config = isset($config['importer']) ? $config['importer'] : $config;

        $config['drivers'] = array_merge($this->drivers, (isset($config['drivers']) ? $config['drivers']: []));
        $config['tasks'] = isset($config['tasks']) ? $config['tasks'] : [];
        // standardizes config key
        foreach ($config['tasks'] as $key => $value) {
            unset($config['tasks'][$key]);
            $config['tasks'][$this->getConfigKey($key)] = $value;
        }

        $this->container = $container;
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
                sprintf('Import task "%s" [alias:%s] is not registered', $taskKey, $configType)
            );
        }

        $config = $this->config['tasks'][$taskKey];
        if (!isset($config['driver'])) {
            throw new Exception\RuntimeException('Driver key must be set in the configuration array');
        }

        // developer should control driver naming personally
        //$driverKey = strtolower($config['driver']);
        $driverKey = $config['driver'];
        if (isset($this->config['drivers'][$driverKey])) {
            $driverClass = $this->config['drivers'][$driverKey];
        } else {
            throw new Exception\RuntimeException('Any driver not registered for ' . $driverKey);
        }

        $config += isset($this->config['driver_options'][$driverKey]) ? $this->config['driver_options'][$driverKey] : [];
        $driver = $this->createDriver($driverClass)
            ->config($config);

        return $driver;
    }

    /**
     * Create driver using Container or "new" operator
     *
     * @param string $driverClass
     * @param array $config
     * @return Driver\DriverInterface
     */
    protected function createDriver($driverClass): Driver\DriverInterface
    {
        return isset($this->container)
            ? $this->container->get($driverClass)
            : new $driverClass();
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
