<?php
/**
 * Importer Factory Test
 *
 * @category Popov
 * @package Popov_Importer
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @datetime: 17.03.2017 16:54
 */
namespace PopovTest\Importer\Factory;

use Mockery;
use PopovTest\Importer\Bootstrap;
use Zend\Stdlib\Exception;
use PHPUnit\Framework\TestCase;
use Popov\Importer\DriverCreator;
use Popov\Importer\Driver\Soap;
use PopovTest\Importer\Fake\DriverDummy;


class FactoryTest extends TestCase
{
    protected $container;

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testThrowExceptionIfTaskNotRegistered()
    {
        //$containter = Bootstrap::getServiceManager();
        $factory = new DriverCreator($container = null, ['tasks' => []]);
        $this->expectException(Exception\RuntimeException::class);
        $factory->create('notexist');
    }

    public function testThrowExceptionIfTaskIssetBuDriverNotRegistered()
    {
        $factory = new DriverCreator($container = null, [
            'tasks' => [
                'discount-card' => []
            ]
        ]);
        $this->expectException(Exception\RuntimeException::class);
        $factory->create('discountcard');
    }

    public function testThrowExceptionIfDriverNotFound()
    {
        $factory = new DriverCreator($container = null, [
            'tasks' => [
                'discount-card' => [
                    'driver' => 'baddriver',
                ]
            ]
        ]);
        $this->expectException(Exception\RuntimeException::class);
        $factory->create('discountcard');
    }

    public function testCreatePreregisteredSoapDriver()
    {
        if (!class_exists('Zend\Soap\Client')) {
            // skip assertion
            return $this->assertTrue(true);
        }

        $factory = new DriverCreator($container = null, [
            'driver_options' => [
                'soap' => [
                    'connection' => [],
                ],
            ],
            'tasks' => [
                'discountcard' => [
                    'driver' => 'Soap',
                ],
            ],
        ]);
        $driver = $factory->create('discountcard');

        $this->assertInstanceOf(Soap::class, $driver);
    }

    public function testCreateCustomDriver()
    {
        $factory = new DriverCreator($container = null, [
            'drivers' => [
                'dummy' => DriverDummy::class
            ],
            'tasks' => [
                'discount-card' => [
                    'driver' => 'dummy',
                ],
            ],
        ]);
        $driver = $factory->create('discountcard');

        $this->assertInstanceOf(DriverDummy::class, $driver);
    }

    public function testCreateCustomDriverThroughContainer()
    {
        $factory = new DriverCreator($container = null, [
            'drivers' => [
                'dummy' => DriverDummy::class
            ],
            'tasks' => [
                'discount-card' => [
                    'driver' => 'dummy',
                ],
            ],
        ]);
        $driver = $factory->create('discountcard');

        $this->assertInstanceOf(DriverDummy::class, $driver);
    }
}
