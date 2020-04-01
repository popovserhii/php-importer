<?php
/**
 * CSV driver
 *
 * @category Popov
 * @package Popov_Importer
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @datetime: 13.05.2016 22:34
 */
namespace Popov\Importer\Driver;

use Popov\Importer\Driver\DriverInterface;

class Csv implements DriverInterface
{
    protected $fp;

    protected $headers;

    protected $source;

    protected $config = [
        'delimiter' => ';',
        'length' => 0
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function source($filename = null)
    {
        if ($filename) {
            $this->source = $filename;

            return $this;
        }

        return $this->source ?? $this->config['source'];
    }

    /**
     * @return bool|resource
     */
    public function resource()
    {
        if (!$this->fp) {
            $this->fp = fopen($this->source(), 'r');
        }

        return $this->fp;
    }

    public function firstRow()
    {
        return 0;
    }

    /**
     * Get first column
     *
     * @return int
     */
    public function firstColumn()
    {
        return 0;
    }

    /**
     * Get last column
     *
     * @return int
     */
    public function lastColumn()
    {
        if (!$this->headers) {
            $this->read(0);
        }

        $lastIndex = count($this->headers) - 1;

        return $lastIndex;
    }

    /**
     * Get last row
     *
     * @return int
     */
    public function lastRow()
    {
        return count(file($this->source()));
    }

    public function read($row, $column = null)
    {
        $value = [];
        if (is_null($column)) {
            if (!$this->headers) {
                $realRow = fgetcsv($this->resource(), 0, $this->config['delimiter']);
                $this->headers = str_replace('-', '_', $realRow);
            }

            if ($row === 0) {
                return $this->headers;
            }

            $value = [];
            $realRow = fgetcsv($this->resource(), 0, $this->config['delimiter']);
            foreach ($this->headers as $index => $title) {
                $value[$title] = $realRow[$index];
            }
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function &config(array $config = [])
    {
        if ($config) {
            $this->config = $config;

            return $this;
        }

        return $this->config;
    }
}
