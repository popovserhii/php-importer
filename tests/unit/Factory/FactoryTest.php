<?php
/**
 * Importer Factory Test
 *
 * @category Agere
 * @package Agere_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 17.03.2017 16:54
 */
namespace AgereTest\Importer\Factory;

use Interop\Container\ContainerInterface;
use Mockery;
use Zend\Stdlib\Exception;
use PHPUnit_Framework_TestCase as TestCase;
use Agere\Importer\DriverFactory;
use Agere\Importer\Driver\Soap;
use AgereTest\Importer\Fake\DriverDummy;


class FactoryTest extends TestCase
{
    protected $container;

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testThrowExceptionIfTaskNotRegistered()
    {
        $factory = new DriverFactory(['tasks' => []]);
        $this->expectException(Exception\RuntimeException::class);
        $factory->create('notexist');
    }

    public function testThrowExceptionIfTaskIssetBuDriverNotRegistered()
    {
        $factory = new DriverFactory([
            'tasks' => [
                'discount-card' => []
            ]
        ]);
        $this->expectException(Exception\RuntimeException::class);
        $factory->create('discountcard');
    }

    public function testThrowExceptionIfDriverNotFound()
    {
        $factory = new DriverFactory([
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
        $factory = new DriverFactory([
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
        $factory = new DriverFactory([
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
        $factory = new DriverFactory([
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
