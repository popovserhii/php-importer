<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2018 Serhii Popov
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category Popov
 * @package Popov_Importer
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
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
        'Excel' => Driver\Excel::class,
        'Libxl' => Driver\LibXl::class,
        'Csv' => Driver\Csv::class,
        'Soap' => Driver\Soap::class,
    ];

    public function __construct(ContainerInterface $container = null, array $config = null)
    {
        $this->setConfig($config);
        $this->container = $container;
    }

    public function setConfig($config)
    {
        //$config = isset($config['importer']) ? $config['importer'] : $config;

        $config['drivers'] = array_merge($this->drivers, (isset($config['drivers']) ? $config['drivers']: []));
        $config['tasks'] = isset($config['tasks']) ? $config['tasks'] : [];
        // standardizes config key
        foreach ($config['tasks'] as $key => $value) {
            unset($config['tasks'][$key]);
            $config['tasks'][$this->getConfigKey($key)] = $value;
        }
        $this->config = $config;

        return $this;
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
