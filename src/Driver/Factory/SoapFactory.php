<?php
/**
 * Importer Soap Factory
 *
 * @category Popov
 * @package Popov_Importer
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @datetime: 06.04.2017 20:50
 */
namespace Popov\Importer\Driver\Factory;

use Interop\Container\ContainerInterface;
use Popov\Importer\Driver\Adapter\SoapCombinedAdapter;
use Popov\Importer\Driver\Soap;

class SoapFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('Config');
        $soapAdapter = $container->get(SoapCombinedAdapter::class);
        $soapDriver = new Soap($config, $soapAdapter);

        return $soapDriver;
    }
}