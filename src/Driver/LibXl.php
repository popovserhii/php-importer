<?php
/**
 * LibXl driver
 *
 * @category Agere
 * @package Agere_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 13.05.2016 13:35
 * @link https://github.com/iliaal/php_excel
 */
namespace Agere\Importer\Driver;

use ExcelBook;

class LibXl implements DriverInterface
{
    protected $filename;

    protected $config = [
        'username' => '',
        'password' => '',
        'locale' => 'UTF-8',
        'sheet' => '',
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function filename($filename = null)
    {
        if ($filename) {
            $this->filename = $filename;

            return $this;
        }

        return $this->filename;
    }

    /**
     * {@inheritDoc}
     */
    public function firstColumn()
    {
        return $this->xlBook()->firstCol();
    }

    /**
     * {@inheritDoc}
     */
    public function lastColumn()
    {
        return $this->xlBook()->lastCol();
    }

    /**
     * {@inheritDoc}
     * @link http://www.libxl.com/spreadsheet.html#lastRow
     */
    public function firstRow()
    {
        return $this->xlBook()->firstRow();
    }

    /**
     * {@inheritDoc}
     */
    public function lastRow()
    {
        return $this->xlBook()->lastRow();
    }

    /**
     * {@inheritDoc}
     */
    public function read($row, $column)
    {
        return $this->xlBook()->read($row, $column);
    }

    /**
     * {@inheritDoc}
     */
    public function config()
    {
        return $this->config;
    }

    protected function xlBook()
    {
        static $xlSheet;

        if (!$xlSheet) {
            $splFile = new \SplFileInfo($this->filename);

            $useXlsxFormat = false;
            if ($splFile->getExtension() === 'xlsx') {
                $useXlsxFormat = true;
            }
            $xlBook = new \ExcelBook($this->config['username'], $this->config['password'], $useXlsxFormat);
            $xlBook->setLocale($this->config['locale']);
            $xlBook->loadFile($splFile->getPathname());

            $xlSheet = trim($this->config['sheet']) ? $xlBook->getSheet($this->config['sheet']) : $xlBook->getSheet();
        }

        return $xlSheet;
    }
}
