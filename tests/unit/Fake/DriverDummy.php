<?php
/**
 * Enter description here...
 *
 * @category Agere
 * @package Agere_<package>
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 04.04.2017 19:55
 */
namespace AgereTest\Importer\Fake;

use Agere\Importer\Driver\DriverInterface;

class DriverDummy implements DriverInterface
{
    public function source($name = null)
    {
        // TODO: Implement source() method.
    }

    public function firstColumn()
    {
        // TODO: Implement firstColumn() method.
    }

    public function lastColumn()
    {
        // TODO: Implement lastColumn() method.
    }

    public function firstRow()
    {
        // TODO: Implement firstRow() method.
    }

    public function lastRow()
    {
        // TODO: Implement lastRow() method.
    }

    public function read($row, $column)
    {
        // TODO: Implement read() method.
    }

    public function config()
    {
        // TODO: Implement config() method.
    }
}