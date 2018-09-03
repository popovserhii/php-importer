<?php
/**
 * Excel driver
 *
 * @category Popov
 * @package Popov_Importer
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @datetime: 13.05.2016 13:35
 * @link https://github.com/PHPOffice/PhpSpreadsheet
 */
namespace Popov\Importer\Driver;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell;

class Excel implements DriverInterface
{
    protected $source;

    protected $config = [
        //'sheet' => 0,
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

        return $this->source;
    }

    /**
     * {@inheritDoc}
     */
    public function firstColumn()
    {
        return 1;
    }

    /**
     * Default value is A1 these means that first row is 1
     *
     * @see \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::$activeCell
     * {@inheritDoc}
     */
    public function firstRow()
    {
        return 1;
    }

    /**
     * {@inheritDoc}
     * @link https://phpspreadsheet.readthedocs.io/en/develop/topics/accessing-cells/#looping-through-cells-using-indexes
     */
    public function lastColumn()
    {
        $xlSheet = $this->xlSheet();
        $highestColumn = $xlSheet->getHighestColumn(); // e.g 'F'
        $highestColumnIndex = Cell\Coordinate::columnIndexFromString($highestColumn); // e.g. 5

        return $highestColumnIndex;
    }

    /**
     * {@inheritDoc}
     * @link https://phpspreadsheet.readthedocs.io/en/develop/topics/accessing-cells/#looping-through-cells-using-indexes
     */
    public function lastRow()
    {
        $xlSheet = $this->xlSheet();
        $highestRow = $xlSheet->getHighestRow();

        return $highestRow + 1;
    }

    /**
     * {@inheritDoc}
     */
    public function read($row, $column = null)
    {
        $xlSheet = $this->xlSheet();

        if (is_null($column)) {
            $highestColumn = $xlSheet->getHighestColumn(); // e.g 'F'
            $highestColumnIndex = Cell\Coordinate::columnIndexFromString($highestColumn); // e.g. 5
            $value = [];
            for ($col = 1; $col <= $highestColumnIndex; ++$col) {
                $value[] = $xlSheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();
            }
        } else {
            $value = $xlSheet->getCellByColumnAndRow($column, $row)->getValue();
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

    protected function xlBook()
    {
        static $spreadsheet;

        if (!$spreadsheet) {
            //$splFile = new \SplFileInfo($this->source);

            $xlBook = IOFactory::createReaderForFile($this->source);
            $xlBook->setReadDataOnly(true);
            $spreadsheet = $xlBook->load($this->source);

            //$reader = IOFactory::createReader($inputFileType);

            //$xlBook->setLocale($this->config['locale']);
            //$xlBook->loadFile($splFile->getPathname());
        }
		
        return $spreadsheet;
    }
	
	protected function xlSheet() 
	{
		static $xlSheet;
		
		if (!$xlSheet) {
			$xlBook = $this->xlBook();

			$xlSheet = isset($this->config['sheet'])
                ? $xlBook->setLoadSheetsOnly($this->config['sheet'])
                : $xlBook->getActiveSheet();
		}
		
		return $xlSheet;
	}
}
