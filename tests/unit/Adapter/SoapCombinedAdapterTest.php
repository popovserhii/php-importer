<?php
/**
 * Enter description here...
 *
 * @category Agere
 * @package Agere_<package>
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 10.04.2017 14:00
 */
namespace AgereTest\Importer\Adapter;

use Mockery;
use Zend\Stdlib\Exception;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Soap\Client as SoapClient;
use Agere\Importer\Driver\Adapter\SoapCombinedAdapter;


class SoapCombinedAdapterTest extends TestCase
{
    protected $container;

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testGetConnection()
    {
        /*
         'soap' => [
                'default' => 'raz',
                'connection' => [
                    'raz_options' => [
                        'wsdl' => 'http://188.0.133.37/raz_web/ws/raz-disabled.1cws?wsdl',
                        'options' => [
                            'login' => 'web_account',
                            'password' => '1q2w3e4r!2',
                            //'login' => 'storage@agere.com.ua',
                            //'password' => 'peraspera',
                            'soap_version' => SOAP_1_2,
                            //'soap_version' => SOAP_1_1,
                            'cache_wsdl' => WSDL_CACHE_NONE,
                        ],
                    ],
                    'viza_options' => [
                        'wsdl' => 'http://188.0.133.37/viza/ws/viza-disabled.1cws?wsdl',
                        'options' => [
                            'login' => 'web_account',
                            'password' => '1q2w3e4r!2',
                            //'login' => 'storage@agere.com.ua',
                            //'password' => 'peraspera',
                            'soap_version' => SOAP_1_2,
                            //'soap_version' => SOAP_1_1,
                            'cache_wsdl' => WSDL_CACHE_NONE,
                        ],
                    ],
                ],
            ],
         */

        $adapter = new SoapCombinedAdapter([
            'bar_connection' => new SoapClient('http://example.com/web/ws/disabled.1cws?wsdl'),
            'foo_connection' => new SoapClient('http://example.com/web/ws/enabled.1cws?wsdl'),
        ]);

        $this->assertEquals(
            'http://example.com/web/ws/disabled.1cws?wsdl',
            $adapter->getConnection('bar_connection')->getWsdl()
        );

        $this->assertEquals(
            'http://example.com/web/ws/enabled.1cws?wsdl',
            $adapter->getConnection('foo_connection')->getWsdl()
        );
    }

    public function testGetDefaultConnection()
    {
        $adapter = new SoapCombinedAdapter([
            'bar_connection' => new SoapClient('http://example.com/web/ws/disabled.1cws?wsdl'),
            'foo_connection' => new SoapClient('http://example.com/web/ws/enabled.1cws?wsdl'),
        ], 'foo_connection');

        $this->assertEquals(
            'http://example.com/web/ws/enabled.1cws?wsdl',
            $adapter->getDefaultConnection()->getWsdl()
        );
    }
}