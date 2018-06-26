<?php
/**
 * Enter description here...
 *
 * @category Popov
 * @package Popov_<package>
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 10.04.2017 14:00
 */
namespace PopovTest\Importer\Adapter;

use Mockery;
use Zend\Stdlib\Exception;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Soap\Client as SoapClient;
use Popov\Importer\Driver\Adapter\SoapCombinedAdapter;


class SoapCombinedAdapterTest extends TestCase
{
    protected $container;

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testGetConnection()
    {
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