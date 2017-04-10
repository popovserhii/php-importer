<?php
/**
 * Importer Soap Factory
 *
 * @category Agere
 * @package Agere_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 06.04.2017 20:50
 */
namespace Agere\Importer\Driver\Factory;

use Interop\Container\ContainerInterface;
use Agere\Importer\Driver\Adapter\SoapCombinedAdapter;
use Agere\Importer\Driver\Soap;

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