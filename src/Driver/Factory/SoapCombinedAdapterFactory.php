<?php
/**
 * Soap Factory
 *
 * @category Popov
 * @package Popov_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 06.04.2017 19:20
 */
namespace Popov\Importer\Driver\Factory;

use AsseticBundle\Exception\RuntimeException;
use Interop\Container\ContainerInterface;

use Zend\Soap\Client as SoapClient;
use Popov\Importer\Driver\Adapter\SoapCombinedAdapter;

class SoapCombinedAdapterFactory
{
    protected $config = [];

    public function __construct($config = null)
    {
        $this->config = $config;
    }

    public function __invoke(ContainerInterface $container = null)
    {
        $config = $this->getConfig($container);
        $clients = [];
        foreach ($config['connection'] as $name => $connection) {
            $clients[$name] = new SoapClient($connection['wsdl'], $connection['options']);
        }
        $soapAdapter = new SoapCombinedAdapter($clients, $config['default_connection']);

        return $soapAdapter;
    }

    protected function getConfig(ContainerInterface $container = null)
    {
        if ($this->config) {
            $config = $this->config;
        } elseif ($container && $container->has('Config')
            && isset($container->get('Config')['importer']['driver_options']['soap'])
        ) {
            $config = $container->get('Config')['importer']['driver_options']['soap'];
        } elseif ($container && $container->has('config')
            && isset($container->get('config')['importer']['driver_options']['soap'])
        ) {
            $config = $container->get('config')['importer']['driver_options']['soap'];
        } else {
            throw new RuntimeException('Cannot find configuration array anywhere');
        }

        return $config;
    }
}