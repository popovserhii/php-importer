<?php
/**
 * Import Factory
 *
 * @category Agere
 * @package Agere_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 19.12.15 17:44
 */
namespace Agere\Importer\Factory;

use Agere\Importer\Driver;

class ImportFactory
{
    protected $drivers = [
        'libxl' => Driver\LibXl::class,
        'csv' => Driver\Csv::class,
    ];

    public function create($config)
    {
        if (!isset($config['driver'])) {
            throw new \Exception('Driver key must be set in the configuration array');
        }

        if (is_array($config['driver'])) {
            $driverKey = $config['driver']['name'];
        } else {
            $driverKey = $config['driver'];
        }

        $driverKey = strtolower($driverKey);
        if (isset($this->drivers[$driverKey])) {
            $driverClass = $this->drivers[$driverKey];
        } elseif (isset($config['class'])) {
            $driverClass = $config['class'];
        } else {
            throw new \Exception('Any driver not registered for ' . $driverKey);
        }

        $driver = new $driverClass($config);

        return $driver;
    }

}