<?php
/**
 * Driver Interface
 *
 * @category Popov
 * @package Popov_Importer
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @datetime: 13.05.2016 13:38
 */
namespace Popov\Importer\Driver;

interface DriverInterface
{
    /**
     * Set/get imported source name
     *
     * Can be file name, api url or other value to source.
     *
     * @param mixed $name
     * @return mixed On set return self object on get return value set previously
     */
    public function source($name = null);

    /**
     * Get first column
     *
     * @return int
     */
    public function firstColumn();

    /**
     * Get last column
     *
     * @return int
     */
    public function lastColumn();

    /**
     * Get first row
     *
     * @return int
     */
    public function firstRow();

    /**
     * Get last row
     *
     * @return int
     */
    public function lastRow();

    /**
     * Get data from a specific cell
     *
     * @param int $row Row index
     * @param int $column Column index. If a column isn't passed than entire row will be returned
     * @return mixed Return value of cell or false if cell not found
     */
    public function read($row, $column = null);

    /**
     * Set/get driver based configuration
     *
     * @param array $config
     * @return mixed On set return self object on get return value set previously
     */
    public function &config(array $config = []);
}
