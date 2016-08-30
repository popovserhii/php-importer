<?php
/**
 * Driver Interface
 *
 * @category Agere
 * @package Agere_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 13.05.2016 13:38
 */
namespace Agere\Importer\Driver;

interface DriverInterface
{
    /**
     * Set/get imported source name
     *
     * Can be file name, api url or other value to source
     *
     * @param string|null $name
     * @return string|self
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
     * Read cell
     *
     * @param int $row
     * @param int $column
     * @return mixed Return cell value
     */
    public function read($row, $column);

    /**
     * Get configuration array
     *
     * @return array
     */
    public function config();
}
