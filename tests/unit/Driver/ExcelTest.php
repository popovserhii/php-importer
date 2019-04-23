<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2019 Serhii Popov
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category Popov
 * @package Popov_<package>
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace PopovTest\Importer\Driver;

use Mockery;
use PHPUnit\Framework\TestCase;
use Popov\Importer\Driver\Excel;
use PopovTest\Importer\Bootstrap;

class ExcelTest extends TestCase
{
   /* public function setUp()
    {
        $gpm = Bootstrap::getServiceManager()->get('DataGridPluginManager');
        $this->factory = new ColumnFactory($gpm, []);
    }*/

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testDefaultFirstRowAndColumnShouldBeEqualOne()
    {
        $driver = new Excel();

        $this->assertEquals(1, $driver->firstRow());
        $this->assertEquals(1, $driver->firstColumn());
    }

    public function testSetStartRowFromConfig()
    {
        $config = [
            "driver_options" => [
                "sheet" => [
                    "skip" => 2,
                ],
            ],
        ];

        $driver = new Excel($config['driver_options']);

        $this->assertEquals(3, $driver->firstRow(), 'Driver should skip 2 first rows and return next one');
    }

    public function testShouldTakePathToFileFromConfigIfSourceIsEmpty()
    {
        $config = [
            "driver_options" => [
                "source" => "data/path/excel.xlsx",
                "sheet" => [
                    "name" => "MIT",
                ],
            ],
        ];

        $driver = new Excel($config['driver_options']);
        $config = $driver->config();

        $this->assertEquals('data/path/excel.xlsx', $driver->source());
        $this->assertEquals('MIT', $config['sheet']['name']);
    }
}