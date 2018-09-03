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

use Interop\Container\ContainerInterface;
use Mockery;
use Zend\Stdlib\Exception;
use PHPUnit_Framework_TestCase as TestCase;
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
        $factory = new DriverCreator(['tasks' => []]);
        $this->expectException(Exception\RuntimeException::class);
        $factory->create('notexist');
    }

    public function testThrowExceptionIfTaskIssetBuDriverNotRegistered()
    {
        $factory = new DriverCreator([
            'tasks' => [
                'discount-card' => []
            ]
        ]);
        $this->expectException(Exception\RuntimeException::class);
        $factory->create('discountcard');
    }

    public function testThrowExceptionIfDriverNotFound()
    {
        $factory = new DriverCreator([
            'tasks' => [
                'discount-card' => [
                    'driver' => 'baddriver',
                ]
            ]
        ]);
        $this->expectException(Exception\RuntimeException::class);
        $factory->create('discountcard');
    }

    public function testCreatePreregisteredDriver()
    {
        $factory = new DriverCreator([
            'driver_options' => [
                'soap' => [
                    'connection' => [],
                ],
            ],
            'tasks' => [
                'discount-card' => [
                    'driver' => 'soap',
                ],
            ],
        ]);
        $driver = $factory->create('discountcard');

        $this->assertInstanceOf(Soap::class, $driver);
    }

    public function testCreateCustomDriver()
    {
        $factory = new DriverCreator([
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
        $factory = new DriverCreator([
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
